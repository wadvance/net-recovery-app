<?php
// Temporal: extrae ezyro-htdocs.zip en el directorio actual y se auto-borra
error_reporting(E_ALL);
ini_set('display_errors', 1);

$target = __DIR__;
$zipFile = $target . '/ezyro-htdocs.zip';

if (!file_exists($zipFile)) {
    echo "NO_ZIP";
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    echo "OPEN_FAIL";
    exit;
}

$ok = $zip->extractTo($target);
$zip->close();

// Borrar el zip y este script
@unlink($zipFile);
@unlink(__FILE__);

echo $ok ? "EXTRACT_OK" : "EXTRACT_FAIL";
