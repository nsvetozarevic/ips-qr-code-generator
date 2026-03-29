<?php

use App\Enums\FrontRoutes;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ParseUploadedPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name(FrontRoutes::Home);
Route::post('/parse', ParseUploadedPdfController::class)->name(FrontRoutes::Parse);
