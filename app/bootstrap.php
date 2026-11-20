<?php

chdir(dirname(__DIR__));

spl_autoload_register(static function ($className) {
    $file = __DIR__ . '/class/' . $className . '.php';
    if (is_file($file)) {
        require $file;
    }
});

defined('URL') || define('URL', rtrim(getenv('IMGE_URL') ?: 'http://localhost:8780', '/'));
defined('IURL') || define('IURL', rtrim(getenv('IMGE_IMAGE_URL') ?: 'http://localhost:8781', '/'));
defined('TURL') || define('TURL', rtrim(getenv('IMGE_THUMB_URL') ?: 'http://localhost:8782', '/'));
defined('UPLOAD_MODE') || define('UPLOAD_MODE', getenv('IMGE_UPLOAD_MODE') ?: 'allowlist');

$alphabet = getenv('IMGE_BASE62');
if ($alphabet !== false && $alphabet !== '') {
    if (!preg_match('/^[A-Za-z0-9]{62}$/D', $alphabet) || count(array_unique(str_split($alphabet))) !== 62) {
        throw new RuntimeException('IMGE_BASE62 must be a permutation of the 62 ASCII letters and digits.');
    }
    define('BASE62', $alphabet);
}

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'imge';
$password = getenv('DB_PASSWORD');
if ($password === false || $password === '') {
    throw new RuntimeException('DB_PASSWORD is required.');
}

$PDO = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4",
    getenv('DB_USER') ?: 'imge', $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
$RENDER = new render(__DIR__ . '/template');
$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
$REQ = explode('/', $path);
array_shift($REQ);
