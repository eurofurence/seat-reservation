<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FloorPlanController;
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
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class,'show'])->name('dashboard');
    Route::get('/profile', [ProfileController::class,'show'])->name('profile.show');
    Route::get('/events',[\App\Http\Controllers\EventController::class,'index'])->name('events.index');
    Route::get('/bookings',[\App\Http\Controllers\BookingController::class,'index'])->name('bookings.index');
    Route::resource('/events/{event}/bookings', \App\Http\Controllers\BookingController::class,[
        'only' => ['show','create','store','destroy','update']
    ]);

    // New comprehensive floor plan editor routes
    Route::prefix('admin/rooms/{room}/floor-plan')->name('floor-plan.')->group(function () {
        Route::get('/editor', [\App\Http\Controllers\FloorPlanEditorController::class, 'show'])->name('editor');
        Route::put('/update', [\App\Http\Controllers\FloorPlanEditorController::class, 'update'])->name('update');
        Route::post('/blocks', [\App\Http\Controllers\FloorPlanEditorController::class, 'createBlock'])->name('blocks.create');
        Route::patch('/blocks/{block}', [\App\Http\Controllers\FloorPlanEditorController::class, 'updateBlock'])->name('blocks.update');
        Route::delete('/blocks/{block}', [\App\Http\Controllers\FloorPlanEditorController::class, 'deleteBlock'])->name('blocks.delete');
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
