<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/');
});

Route::get('/admin/{path?}', function ($path = null) {
    $candidates = [
        public_path('admin' . ($path ? '/' . $path : '/index.html')),
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $mime = [
                'webmanifest' => 'application/manifest+json',
                'js' => 'text/javascript',
                'json' => 'application/json',
                'png' => 'image/png',
                'svg' => 'image/svg+xml',
                'css' => 'text/css',
                'html' => 'text/html',
            ][pathinfo($file, PATHINFO_EXTENSION)] ?? null;
            return response()->file($file, $mime ? ['Content-Type' => $mime] : []);
        }
    }
    $index = public_path('admin/index.html');
    return is_file($index)
        ? response()->file($index)
        : response('Admin panel not built', 404);
})->where('path', '.*');

Route::get('/panel/{path?}', function ($path = null) {
    $candidates = [
        base_path('panel' . ($path ? '/' . $path : '/index.html')),
        public_path('panel' . ($path ? '/' . $path : '/index.html')),
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            return response()->file($file);
        }
    }
    $index = public_path('panel/index.html');
    return is_file($index)
        ? response()->file($index)
        : response('Panel not built', 404);
})->where('path', '.*');

Route::get('/whatsapp/qr', function () {
    // QR de TU Evolution propia (sin terceros). Requiere EVOLUTION_BASE_URL + EVOLUTION_API_KEY.
    $instance = request('instance', config('services.evolution.instance', 'net-recovery'));
    $base = rtrim((string) config('services.evolution.base_url'), '/');
    $key = (string) config('services.evolution.api_key');
    if ($base === '' || $key === '') {
        return response('Evolution no configurada: define EVOLUTION_BASE_URL y EVOLUTION_API_KEY', 503);
    }
    try {
        $http = Http::timeout(25)->withHeaders(['apikey' => $key, 'Content-Type' => 'application/json']);
        $state = $http->get("{$base}/instance/connectionState/{$instance}")->json();
        $stateName = strtolower((string) ($state['instance']['state'] ?? $state['state'] ?? ''));
        if ($stateName === 'open') {
            return response('<h2 style="font-family:sans-serif">WhatsApp conectado (' . e($instance) . ')</h2>');
        }
        $conn = $http->get("{$base}/instance/connect/{$instance}")->json();
        $qr = $conn['base64'] ?? $conn['qrcode']['base64'] ?? null;
        if (!$qr) {
            return response('No se pudo obtener el QR (revisa la instancia en Evolution)', 502);
        }
        return response('<html><head><meta http-equiv="refresh" content="45"><title>Vincular WhatsApp</title></head>'
            . '<body style="font-family:sans-serif;text-align:center">'
            . '<h2>Escanea con WhatsApp &gt; Dispositivos vinculados</h2>'
            . '<img src="' . e($qr) . '" style="width:320px;height:320px">'
            . '<p>Instancia: ' . e($instance) . ' · se actualiza cada 45 s</p>'
            . '</body></html>');
    } catch (\Throwable $e) {
        return response('Error contactando Evolution: ' . $e->getMessage(), 502);
    }
});

Route::get('/{path?}', function () {
    return response()->json(['message' => 'API only - visit /admin for the dashboard']);
})->where('path', '.*');
