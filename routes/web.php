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

Route::redirect('/', '/auth/login')->middleware('auth')->name('welcome');
Route::redirect('/login', '/auth/login')->middleware('auth')->name('login');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class,'show'])->name('dashboard');
    Route::get('/profile', [ProfileController::class,'show'])->name('profile.show');
    Route::get('/events',[\App\Http\Controllers\EventController::class,'index'])->name('events.index');
    Route::get('/bookings',[\App\Http\Controllers\BookingController::class,'index'])->name('bookings.index');
    Route::resource('/events/{event}/bookings', \App\Http\Controllers\BookingController::class,[
        'only' => ['show','create','store','destroy','update']
    ]);
    
    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
        Route::get('/rooms/{room}/layout', [\App\Http\Controllers\Admin\RoomLayoutController::class, 'edit'])->name('rooms.layout');
        Route::put('/rooms/{room}/layout', [\App\Http\Controllers\Admin\RoomLayoutController::class, 'update'])->name('rooms.layout.update');
    });
});

// Auth Group
Route::prefix('/auth')->name('auth.')->group(function () {
    Route::get('/login',[\App\Http\Controllers\AuthController::class,'show'])->middleware('guest')->name('login');
    Route::post('/login',[\App\Http\Controllers\AuthController::class,'login'])->middleware('guest')->name('login.redirect');
    Route::get('/callback', [\App\Http\Controllers\AuthController::class,'loginCallback'])->middleware('guest')->name('login.callback');
    Route::post('/logout', [\App\Http\Controllers\AuthController::class,'logout'])->middleware('auth')->name('logout');
    Route::get('/frontchannel-logout', [\App\Http\Controllers\AuthController::class,'logoutCallback'])->name('logout.callback');
});
