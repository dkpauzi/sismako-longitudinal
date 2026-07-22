<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StrukturorganisasiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GalerikegiatanController;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/', [DashboardController::class, 'index'])->name('home');

// SEO: sitemap dinamis (didaftarkan di robots.txt & Google Search Console).
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
Route::get('/struktur-organisasi', [StrukturorganisasiController::class, 'index']);
Route::get('/galeri-kegiatan', [GalerikegiatanController::class, 'index']);

Route::middleware('auth')->group(function () {
    Route::get('/rapor/print/{homeroom}/{student}', [\App\Http\Controllers\RaporPrintController::class, 'show'])
        ->name('rapor.print');
});
