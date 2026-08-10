<?php
/// router.php -> SPA sobre dist (sin Vite HMR). Sirve assets; fallback index.html.
$publicDir = __DIR__;
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$asset = $publicDir . $uri;

// assets existentes (js/css/favicon) -> dejar que PHP los sirva
if ($uri !== '/' && $uri !== '' && $uri !== '/index.html' && file_exists($asset) && !is_dir($asset)) {
    return false;
}
// Todo lo demás -> SPA index.html (login, tareas, clientes, etc.)
include $publicDir . '/index.html';
