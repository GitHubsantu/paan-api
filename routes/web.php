<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');
Route::get('/download', DownloadController::class)->middleware('throttle:30,1')->name('download');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /admin\nDisallow: /api\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']));
Route::get('/sitemap.xml', fn () => response(view('sitemap')->render(), 200, ['Content-Type' => 'application/xml']));
