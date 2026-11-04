<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'version' => 'v1',
    'documentation' => url('/docs/api'),
]));

Route::get('/docs/api', fn () => view('swagger'))->name('swagger');

