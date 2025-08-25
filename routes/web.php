<?php

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

// Health check endpoint
Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'Seat Planning System'
    ]);
});

Route::redirect('/', '/auth/login')->name('welcome');
Route::redirect('/login', '/auth/login')->name('login');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class,'show'])->name('dashboard');
    Route::get('/events',[\App\Http\Controllers\EventController::class,'index'])->name('events.index');
    Route::get('/bookings',[\App\Http\Controllers\BookingController::class,'index'])->name('bookings.index');
    Route::get('/events/{event}/bookings/confirmed/{code}', [\App\Http\Controllers\BookingController::class, 'confirmed'])->name('bookings.confirmed');
    Route::resource('/events/{event}/bookings', \App\Http\Controllers\BookingController::class,[
        'only' => ['show','create','store','destroy','update']
    ]);
    
    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', \App\Http\Middleware\ShareAdminData::class])->group(function () {
        // Dashboard
        Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/lookup-booking-code', [\App\Http\Controllers\Admin\AdminController::class, 'lookupBookingCode'])->name('lookup-booking-code');
        
        // Events
        Route::get('/events', [\App\Http\Controllers\Admin\EventAdminController::class, 'index'])->name('events.index');
        Route::post('/events', [\App\Http\Controllers\Admin\EventAdminController::class, 'store'])->name('events.store');
        Route::put('/events/{event}', [\App\Http\Controllers\Admin\EventAdminController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [\App\Http\Controllers\Admin\EventAdminController::class, 'destroy'])->name('events.destroy');
        Route::get('/events/{event}', [\App\Http\Controllers\Admin\EventAdminController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/export', [\App\Http\Controllers\Admin\EventAdminController::class, 'export'])->name('events.export');
        Route::get('/events/{event}/seating-cards', [\App\Http\Controllers\Admin\EventAdminController::class, 'seatingCards'])->name('events.seating-cards');
        Route::post('/events/{event}/manual-booking', [\App\Http\Controllers\Admin\EventAdminController::class, 'manualBooking'])->name('events.manual-booking');
        Route::post('/events/{event}/toggle-pickup', [\App\Http\Controllers\Admin\EventAdminController::class, 'togglePickup'])->name('events.toggle-pickup');
        Route::put('/events/{event}/bookings/{booking}', [\App\Http\Controllers\Admin\EventAdminController::class, 'updateBooking'])->name('events.update-booking');
        Route::delete('/events/{event}/bookings/{booking}', [\App\Http\Controllers\Admin\EventAdminController::class, 'deleteBooking'])->name('events.delete-booking');
        
        // Rooms
        Route::get('/rooms', [\App\Http\Controllers\Admin\RoomAdminController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [\App\Http\Controllers\Admin\RoomAdminController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}/edit', [\App\Http\Controllers\Admin\RoomAdminController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [\App\Http\Controllers\Admin\RoomAdminController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [\App\Http\Controllers\Admin\RoomAdminController::class, 'destroy'])->name('rooms.destroy');
        
        // Floor Plan Editor (use existing controller)
        Route::get('/rooms/{room}/layout', [\App\Http\Controllers\Admin\RoomLayoutController::class, 'edit'])->name('rooms.layout');
        Route::put('/rooms/{room}/layout', [\App\Http\Controllers\Admin\RoomLayoutController::class, 'update'])->name('rooms.layout.update');
        Route::post('/rooms/{room}/blocks', [\App\Http\Controllers\Admin\RoomLayoutController::class, 'createBlock'])->name('rooms.blocks.create');
        Route::delete('/rooms/{room}/blocks/{block}', [\App\Http\Controllers\Admin\RoomLayoutController::class, 'deleteBlock'])->name('rooms.blocks.delete');
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
