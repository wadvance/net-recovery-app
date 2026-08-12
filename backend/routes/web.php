<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/');
});

Route::get('/admin/{path?}', function () {
    return file_exists(public_path('admin/index.html'))
        ? response()->file(public_path('admin/index.html'))
        : response('Admin panel not built', 404);
})->where('path', '.*');

Route::get('/{path?}', function () {
    return response()->json(['message' => 'API only - visit /admin for the dashboard']);
})->where('path', '.*');
