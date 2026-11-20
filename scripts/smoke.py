"""Exercise the HTTP upload and delivery flow on a fresh test instance."""
import argparse
from concurrent.futures import ThreadPoolExecutor
import hashlib
from ipaddress import IPv4Address
import json
import os
import secrets
import struct
import subprocess
import tempfile
import time
import zlib

SITE = os.getenv("IMGE_URL", "http://localhost:8780").rstrip("/")
IMAGE = os.getenv("IMGE_IMAGE_URL", "http://localhost:8781").rstrip("/")
THUMB = os.getenv("IMGE_THUMB_URL", "http://localhost:8782").rstrip("/")


def request(url, *args, expected=200):
    result = subprocess.check_output(
        ["curl", "-sS", "--max-time", "10", "-w", "%{http_code}", *args, url]
    )
    if int(result[-3:]) != expected:
        raise RuntimeError(f"{url}: expected {expected}, got {result[-3:].decode()}")
    return result[:-3]


def chunk(name, data):
    return struct.pack("!I", len(data)) + name + data + struct.pack("!I", zlib.crc32(name + data))


def ticket():
    return json.loads(request(SITE + "/ticket"))["ticket"]


def upload(file, token, *, expected=200):
    body = request(
        SITE + "/upload/" + token,
        "-F", f"file=@{file.name};filename=test.txt;type=application/octet-stream",
        expected=expected,
    )
    return json.loads(body) if expected == 200 else body


def compose(*args):
    return subprocess.check_output(["docker", "compose", *args], text=True, timeout=30).strip()


def sql(statement):
    return compose(
        "exec", "-T", "db", "sh", "-c",
        'exec mariadb -u root -p"$MARIADB_ROOT_PASSWORD" -N -B "$MARIADB_DATABASE" -e "$1"',
        "sh", statement,
    )


def media(*args):
    return compose("exec", "-T", "app", *args)


def image_id(content):
    digest = hashlib.sha256(content).hexdigest()
    return sql(f"SELECT id FROM imge WHERE digest = '{digest}'")


def reconcile():
    # Preserve the report even when discrepancies cause the command to exit 1.
    return media("sh", "-c", "php app/reconcile-storage.php; status=$?; test \"$status\" -le 1")


def storage_path(kind, image, extension):
    number = int(image)
    return f"storage/{kind}/{number % 10000 // 100}/{number % 100}/{number}.{extension}"


def regression_checks(file, png, url, thumbnail_url):
    used = ticket()
    assert upload(file, used) == {"url": url, "duplicate": True}
    assert upload(file, used)["error"] == "Ticket Not Valid"
    expired = ticket()
    sql(f"UPDATE ticket SET expire = NOW() - INTERVAL 1 SECOND WHERE ticket = '{expired}'")
    assert upload(file, expired)["error"] == "Ticket Not Valid"

    thumbnail = storage_path("t", image_id(png), "png")
    media("rm", thumbnail)
    request(thumbnail_url, expected=404)
    assert f"missing\t{thumbnail}" in reconcile().splitlines()
    retry = ticket()
    media("mkdir", thumbnail)
    try:
        assert upload(file, retry)["error"] == "Unable to Save Image"
        assert request(url) == png
    finally:
        media("rmdir", thumbnail)
    assert upload(file, retry) == {"url": url}
    assert request(thumbnail_url).startswith(signature)

    with tempfile.NamedTemporaryFile() as other:
        unique = png[:-12] + chunk(b"tEXt", b"Comment\0" + secrets.token_hex(16).encode()) + png[-12:]
        other.write(unique)
        other.flush()
        tokens = [ticket(), ticket()]
        with ThreadPoolExecutor(max_workers=2) as pool:
            results = list(pool.map(lambda token: upload(other, token), tokens))
        assert sum(result.get("duplicate") is True for result in results) == 1, results
        assert results[0]["url"] == results[1]["url"]
        assert request(results[0]["url"]) == unique

    with tempfile.NamedTemporaryFile() as boundary:
        limit = 8 * 1024 * 1024
        prefix = png[:-12]
        metadata = b"Comment\0"
        boundary.write(prefix + chunk(b"tEXt", metadata.ljust(limit - len(png) - 12, b"a")) + png[-12:])
        boundary.flush()
        assert boundary.tell() == limit
        assert "url" in upload(boundary, ticket())
        boundary.write(b"\0")
        boundary.flush()
        assert upload(boundary, ticket())["error"] == "Size Too Big"

    interrupted_upload(png)


def interrupted_upload(png):
    content = png[:-12] + chunk(b"tEXt", b"Comment\0" + secrets.token_hex(16).encode()) + png[-12:]
    with tempfile.NamedTemporaryFile() as file:
        file.write(content)
        file.flush()
        token = ticket()
        # This hook runs after both files have been published and before commit.
        sql(f"""CREATE TRIGGER smoke_pause_upload BEFORE UPDATE ON ticket FOR EACH ROW
                SET NEW.status = NEW.status + IF(NEW.ticket = '{token}' AND NEW.status = 1, SLEEP(15), 0)""")
        try:
            with ThreadPoolExecutor(max_workers=1) as pool:
                pending = pool.submit(upload, file, token, expected=502)
                deadline = time.monotonic() + 7
                while not sql("SELECT ID FROM information_schema.PROCESSLIST WHERE STATE = 'User sleep'"):
                    if pending.done() or time.monotonic() >= deadline:
                        raise RuntimeError("Upload did not reach the pre-commit pause")
                    time.sleep(0.1)
                workers = media("sh", "-c", "for f in /proc/[0-9]*/cmdline; do "
                                "if grep -aq '^php-fpm: pool www' \"$f\"; then "
                                'pid=${f#/proc/}; kill -KILL "${pid%/cmdline}" && echo "$pid"; fi; done')
                assert workers, "No PHP-FPM workers were stopped"
                pending.result()
        finally:
            sql("DROP TRIGGER smoke_pause_upload")
        assert not image_id(content)
        report = reconcile().splitlines()
        assert sum(line.startswith("orphan\t") for line in report) == 2, report
        assert not any(line.startswith(("temporary\t", "missing\t")) for line in report), report
        result = upload(file, token)
        assert request(result["url"]) == content


def container_request(path, *, png=None):
    # A request from PHP's container reaches Nginx from a distinct TCP peer.
    source = r"""
    $options = ['timeout' => 10];
    $image = stream_get_contents(STDIN);
    if ($image !== '') {
        $boundary = bin2hex(random_bytes(16));
        $options['method'] = 'POST';
        $options['header'] = "Content-Type: multipart/form-data; boundary=$boundary";
        $options['content'] = "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"test.png\"\r\n"
            . "Content-Type: image/png\r\n\r\n" . $image . "\r\n--$boundary--\r\n";
    }
    $body = file_get_contents('http://web:8080' . $argv[1], false, stream_context_create(['http' => $options]));
    if ($body === false || !str_contains($http_response_header[0], ' 200 ')) {
        exit(1);
    }
    echo $body;
    """
    return subprocess.run(
        ["docker", "compose", "exec", "-T", "app", "php", "-r", source, path],
        input=png or b"", stdout=subprocess.PIPE, check=True, timeout=20,
    ).stdout


def allowlist_checks(file, png, url):
    # Learn the source address seen by Nginx rather than assuming a Docker gateway.
    probe = ticket()
    host_ip = IPv4Address(sql(f"SELECT seed FROM ticket WHERE ticket = '{probe}'"))
    sql("UPDATE allowip SET status = -1")
    subprocess.run(
        ["docker", "compose", "up", "-d", "--no-deps", "--force-recreate", "app", "web"],
        env=dict(os.environ, IMGE_UPLOAD_MODE="allowlist"), check=True, timeout=60,
    )
    wait_for_home()
    container_ip = IPv4Address(media("php", "-r", "echo gethostbyname(gethostname());"))
    assert host_ip != container_ip

    denied = "Your IP is not allowed."
    assert b'imge.tw' in request(SITE + "/")
    assert request(url) == png
    assert json.loads(request(SITE + "/ticket"))["error"] == denied
    assert upload(file, probe)["error"] == denied

    for address in (host_ip, container_ip):
        ip = int(address)
        sql(f"INSERT INTO allowip (start, end, comment) VALUES ({ip}, {ip}, 'smoke test')")
    host_token = ticket()
    container_token = json.loads(container_request("/ticket"))["ticket"]
    assert sql(f"SELECT seed FROM ticket WHERE ticket = '{host_token}'") == str(host_ip)
    assert sql(f"SELECT seed FROM ticket WHERE ticket = '{container_token}'") == str(container_ip)

    crossed = container_request("/upload/" + host_token, png=png)
    assert json.loads(crossed)["error"] == "Ticket Not Valid"
    assert upload(file, host_token) == {"url": url, "duplicate": True}
    unique = png[:-12] + chunk(b"tEXt", b"Comment\0" + secrets.token_hex(16).encode()) + png[-12:]
    own = json.loads(container_request("/upload/" + container_token, png=unique))
    assert "duplicate" not in own and own["url"] != url, own
    assert request(own["url"]) == unique


def wait_for_home():
    for attempt in range(30):
        try:
            assert b"imge.tw" in request(SITE + "/")
            return
        except (subprocess.CalledProcessError, RuntimeError):
            if attempt == 29:
                raise
            time.sleep(1)


parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--isolated-compose", action="store_true", help="enable fault injection on a disposable Compose project")
options = parser.parse_args()
if options.isolated_compose:
    project = os.getenv("COMPOSE_PROJECT_NAME", "")
    if not project.startswith("imge-smoke-"):
        parser.error("--isolated-compose requires a disposable COMPOSE_PROJECT_NAME beginning with imge-smoke-")
    for endpoint, port in [(SITE, 8080), (IMAGE, 8081), (THUMB, 8082)]:
        published_port = compose("port", "web", str(port)).rsplit(":", 1)[1]
        if endpoint not in {f"http://localhost:{published_port}", f"http://127.0.0.1:{published_port}"}:
            parser.error(f"{endpoint} must point to the disposable Compose project")
    assert reconcile() == "Storage reconciliation found no discrepancies."


wait_for_home()

assert b"UIkit" in request(SITE + "/js/uikit.min.js")
assert json.loads(request(SITE + "/info"))["top"] == []
request(IMAGE + "/storage/i/no-file.png", expected=404)
request(THUMB + "/storage/t/no-file.png", expected=404)

signature = b"\x89PNG\r\n\x1a\n"
png = signature + chunk(b"IHDR", struct.pack("!IIBBBBB", 1, 1, 8, 2, 0, 0, 0))
png += chunk(b"IDAT", zlib.compress(b"\0" + secrets.token_bytes(3)))
png += chunk(b"tEXt", b"Comment\0" + secrets.token_hex(16).encode()) + chunk(b"IEND", b"")

with tempfile.NamedTemporaryFile() as file:
    file.write(png)
    file.flush()

    uploaded = upload(file, ticket())
    url = uploaded["url"]
    assert url.startswith(IMAGE + "/") and url.endswith(".png")
    assert hashlib.sha256(request(url)).digest() == hashlib.sha256(png).digest()
    recent = next(item for item in json.loads(request(SITE + "/recent")) if item["i"] == url)
    assert recent["t"].startswith(THUMB + "/")
    assert request(recent["t"]).startswith(signature)
    assert upload(file, ticket()) == {"url": url, "duplicate": True}
    if options.isolated_compose:
        regression_checks(file, png, url, recent["t"])
        print("Ticket, size boundary, parallel upload, repair and interruption checks passed.")
        allowlist_checks(file, png, url)
        print("Allowlisted upload, public browsing and source-bound ticket checks passed.")

print("Homepage, upload, original, thumbnail and duplicate checks passed.")
