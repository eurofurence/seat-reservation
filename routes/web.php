<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::inertia('/', 'Welcome')->middleware('auth')->name('welcome');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class,'show'])->name('dashboard');
});

// Auth Group
Route::prefix('/auth')->name('auth.')->group(function () {
    Route::get('/login',[\App\Http\Controllers\AuthController::class,'show'])->name('login');
    Route::post('/login',[\App\Http\Controllers\AuthController::class,'login'])->name('login.redirect');
    Route::get('/callback', [\App\Http\Controllers\AuthController::class,'loginCallback'])->name('login.callback');
    Route::post('/logout', [\App\Http\Controllers\AuthController::class,'logout'])->middleware('auth')->name('logout');
    Route::get('/frontchannel-logout', [\App\Http\Controllers\AuthController::class,'logoutCallback'])->name('logout.callback');
});
