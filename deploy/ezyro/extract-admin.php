<?php
// Temporal: reemplaza htdocs/admin/ y extrae admin.zip ahi
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);

$root = __DIR__;
$target = $root . '/admin';
$zipFile = $root . '/admin.zip';

// Borrar admin/ anterior
if (is_dir($target)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    }
    @rmdir($target);
}

if (!file_exists($zipFile)) { echo "NO_ZIP"; exit; }

@mkdir($target, 0755, true);

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) { echo "OPEN_FAIL"; exit; }
$ok = $zip->extractTo($target);
$zip->close();

@unlink($zipFile);
@unlink(__FILE__);

echo $ok ? "ADMIN_EXTRACT_OK" : "EXTRACT_FAIL";
