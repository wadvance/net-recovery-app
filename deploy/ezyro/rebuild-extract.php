<?php
// Temporal: reensambla partes de netrecovery.zip y extrae en htdocs, luego se auto-borra
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 600);
set_time_limit(600);

$target = __DIR__;
$partsDir = $target . '/parts';
$zipFile = $target . '/netrecovery-rebuilt.zip';

// 1. Reensamblar
$out = fopen($zipFile, 'wb');
if (!$out) { echo "OPEN_OUT_FAIL"; exit; }

$part = 1;
$allOk = true;
while (true) {
    $p = sprintf('%s/netrecovery.zip.%03d', $partsDir, $part);
    if (!file_exists($p)) break;
    $in = fopen($p, 'rb');
    if (!$in) { echo "OPEN_PART_FAIL:$part"; $allOk = false; break; }
    stream_copy_to_stream($in, $out);
    fclose($in);
    $part++;
}
fclose($out);

if (!$allOk) exit;

// 2. Verificar tamaño (debe coincidir con zip original ~27MB)
$size = filesize($zipFile);
echo "REBUILT_SIZE: " . $size . "\n";

// 3. Extraer
$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    echo "OPEN_ZIP_FAIL";
    exit;
}
$ok = $zip->extractTo($target);
$zip->close();

// 4. Limpiar: zip, partes y este script
@unlink($zipFile);
foreach (glob($partsDir . '/netrecovery.zip.*') as $f) { @unlink($f); }
@rmdir($partsDir);
@unlink(__FILE__);

echo $ok ? "EXTRACT_OK" : "EXTRACT_FAIL";
