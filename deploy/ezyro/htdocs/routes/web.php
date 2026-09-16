<?php

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
    $agent = request('agent', 'eddie_68681638');
    return redirect()->away('https://netrecovery.alwaysdata.net/baileys/whatsapp/qr?agent='.$agent);
});

Route::get('/seed-admin', function () {
    $secret = request('key');
    if ($secret !== 'netrecovery-seed-2026') {
        return response()->json(['error' => 'Forbidden'], 403);
    }
    $results = [];
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $results[] = \Illuminate\Support\Facades\Artisan::output();
    $admin = \App\Models\User::where('email', 'admin@recovery.local')->first();
    $results[] = $admin ? "Admin found: {$admin->email} (role={$admin->role})" : "Admin NOT found";
    return response()->json(['results' => $results]);
});

Route::get('/{path?}', function () {
    return response()->json(['message' => 'API only - visit /admin for the dashboard']);
})->where('path', '.*');
