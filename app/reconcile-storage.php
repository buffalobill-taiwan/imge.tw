<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$expected = [];
$issues = 0;
$report = static function (string $kind, string $path) use (&$issues): void {
    ++$issues;
    echo $kind, "\t", $path, PHP_EOL;
};

try {
    require __DIR__ . '/bootstrap.php';
    $roots = ['storage/i', 'storage/t'];
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) {
            throw new RuntimeException("Cannot read media directory: $root");
        }
    }

    $rows = $PDO->query('SELECT id, ext, status FROM imge');
    foreach ($rows as $row) {
        $paths = [
            util::id2ImgePath($row['id'], $row['ext']),
            util::id2ThumbPath($row['id']),
        ];
        foreach ($paths as $path) {
            $expected[$path] = true;
            if ((int) $row['status'] === 0 && !is_file($path)) {
                $report('missing', $path);
            }
        }
    }

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (str_starts_with($file->getFilename(), '.upload-')) {
                $report('temporary', $path);
            } elseif (!isset($expected[$path])) {
                $report('orphan', $path);
            }
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Storage reconciliation failed: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}

if ($issues === 0) {
    echo "Storage reconciliation found no discrepancies.\n";
}
exit($issues > 0 ? 1 : 0);
