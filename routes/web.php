<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StrukturorganisasiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GalerikegiatanController;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/struktur-organisasi', [StrukturorganisasiController::class, 'index']);
Route::get('/galeri-kegiatan', [GalerikegiatanController::class, 'index']);
