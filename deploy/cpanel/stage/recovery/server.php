<?php
// Router del PHP built-in server: sirve archivos estáticos de public/ y el resto va a Laravel.
$publicPath = __DIR__ . '/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && $uri !== '' && file_exists($publicPath . $uri) && !is_dir($publicPath . $uri)) {
    return false;
}

require $publicPath . '/index.php';