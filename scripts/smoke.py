"""Exercise the HTTP upload and delivery flow on a fresh test instance."""
import hashlib
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


for attempt in range(30):
    try:
        assert b"imge.tw" in request(SITE + "/")
        break
    except (subprocess.CalledProcessError, RuntimeError):
        if attempt == 29:
            raise
        time.sleep(1)

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

    def upload():
        ticket = json.loads(request(SITE + "/ticket"))["ticket"]
        return json.loads(request(
            SITE + "/upload/" + ticket,
            "-F", f"file=@{file.name};filename=test.txt;type=application/octet-stream",
        ))

    uploaded = upload()
    url = uploaded["url"]
    assert url.startswith(IMAGE + "/") and url.endswith(".png")
    assert hashlib.sha256(request(url)).digest() == hashlib.sha256(png).digest()
    recent = next(item for item in json.loads(request(SITE + "/recent")) if item["i"] == url)
    assert recent["t"].startswith(THUMB + "/")
    assert request(recent["t"]).startswith(signature)
    assert upload() == {"url": url, "duplicate": True}

print("Homepage, upload, original, thumbnail and duplicate checks passed.")
