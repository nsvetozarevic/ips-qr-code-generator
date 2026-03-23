<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->noContent())->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => response()->noContent())->name('dashboard');
});

require __DIR__.'/settings.php';
