<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AutoPlaceSeatsController;
use App\Http\Controllers\Admin\ConfirmImportController;
use App\Http\Controllers\Admin\DeleteBookingController;
use App\Http\Controllers\Admin\EventAdminController;
use App\Http\Controllers\Admin\ExportAllBookingsController;
use App\Http\Controllers\Admin\ExportBookingsController;
use App\Http\Controllers\Admin\GlobalImportTemplateController;
use App\Http\Controllers\Admin\GlobalProposeImportController;
use App\Http\Controllers\Admin\ImportPreviewController;
use App\Http\Controllers\Admin\ImportTemplateController;
use App\Http\Controllers\Admin\ManualBookingController;
use App\Http\Controllers\Admin\ProposeImportController;
use App\Http\Controllers\Admin\RoomAdminController;
use App\Http\Controllers\Admin\RoomLayoutController;
use App\Http\Controllers\Admin\SeatingCardsController;
use App\Http\Controllers\Admin\TogglePickupController;
use App\Http\Controllers\Admin\UpdateBookingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Middleware\ShareAdminData;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
        'service' => 'Seat Planning System',
    ]);
});

Route::redirect('/', '/auth/login')->name('welcome');
Route::redirect('/login', '/auth/login')->name('login');

// E2E-only login bypass for Playwright tests (tests/e2e/auth.setup.ts). Committed
// and independent from any manual local-dev login shortcut — always available in
// local, 404s everywhere else. Logs into the deterministic e2e-admin/e2e-user
// accounts seeded by `php artisan e2e:seed` (SeedE2EData) — it does not create them.
if (app()->environment('local')) {
    Route::get('/e2e-login', function (Request $request) {
        $asUser = $request->query('as') === 'user';

        $user = User::where('remote_id', $asUser ? 'e2e-user' : 'e2e-admin')->firstOrFail();

        Auth::login($user);

        return redirect()->route($asUser ? 'dashboard' : 'admin.dashboard');
    })->name('e2e.login');
}

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/events/{event}/bookings/confirmed/{code}', [BookingController::class, 'confirmed'])->name('bookings.confirmed');
    Route::resource('/events/{event}/bookings', BookingController::class, [
        'only' => ['show', 'create', 'store', 'destroy', 'update'],
    ]);

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', ShareAdminData::class])->group(function () {
        // Dashboard
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/lookup-booking-code', [AdminController::class, 'lookupBookingCode'])->name('lookup-booking-code');

        // Events
        Route::get('/events', [EventAdminController::class, 'index'])->name('events.index');
        Route::get('/events/export-all', ExportAllBookingsController::class)->name('events.export-all');
        Route::get('/import-bookings/template', GlobalImportTemplateController::class)->name('import-bookings.template');
        Route::post('/import-bookings', GlobalProposeImportController::class)->name('import-bookings.propose');
        Route::post('/events', [EventAdminController::class, 'store'])->name('events.store');
        Route::put('/events/{event}', [EventAdminController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [EventAdminController::class, 'destroy'])->name('events.destroy');
        Route::get('/events/{event}', [EventAdminController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/export', ExportBookingsController::class)->name('events.export');
        Route::get('/events/{event}/seating-cards', SeatingCardsController::class)->name('events.seating-cards');
        Route::post('/events/{event}/manual-booking', ManualBookingController::class)->name('events.manual-booking');
        Route::get('/events/{event}/import-bookings/template', ImportTemplateController::class)->name('events.import-bookings.template');
        Route::post('/events/{event}/import-bookings', ProposeImportController::class)->name('events.import-bookings.propose');
        Route::get('/events/{event}/import-bookings/preview', ImportPreviewController::class)->name('events.import-bookings.preview');
        Route::post('/events/{event}/import-bookings/autoplace', AutoPlaceSeatsController::class)->name('events.import-bookings.autoplace');
        Route::post('/events/{event}/import-bookings/confirm', ConfirmImportController::class)->name('events.import-bookings.confirm');
        Route::post('/events/{event}/toggle-pickup', TogglePickupController::class)->name('events.toggle-pickup');
        Route::put('/events/{event}/bookings/{booking}', UpdateBookingController::class)->name('events.update-booking');
        Route::delete('/events/{event}/bookings/{booking}', DeleteBookingController::class)->name('events.delete-booking');

        // Rooms
        Route::get('/rooms', [RoomAdminController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomAdminController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}/edit', [RoomAdminController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [RoomAdminController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [RoomAdminController::class, 'destroy'])->name('rooms.destroy');

        // Floor Plan Editor (use existing controller)
        Route::get('/rooms/{room}/layout', [RoomLayoutController::class, 'edit'])->name('rooms.layout');
        Route::put('/rooms/{room}/layout', [RoomLayoutController::class, 'update'])->name('rooms.layout.update');
        Route::post('/rooms/{room}/blocks', [RoomLayoutController::class, 'createBlock'])->name('rooms.blocks.create');
        Route::delete('/rooms/{room}/blocks/{block}', [RoomLayoutController::class, 'deleteBlock'])->name('rooms.blocks.delete');
    });
});

// Auth Group
Route::prefix('/auth')->name('auth.')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->middleware('guest')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login.redirect');
    Route::get('/callback', [AuthController::class, 'loginCallback'])->middleware('guest')->name('login.callback');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
    Route::get('/frontchannel-logout', [AuthController::class, 'logoutCallback'])->name('logout.callback');
});
