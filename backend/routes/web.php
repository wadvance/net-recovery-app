<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $file = file_exists(public_path('index.html')) ? public_path('index.html') : public_path('frontend/index.html');
    return response()->file($file);
})->where('path', '.*');