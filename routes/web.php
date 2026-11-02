<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/auth.php';

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api|sanctum|up|storage).*$');
