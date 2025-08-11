<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API endpoint to get seat data for a specific row (reduces Inertia payload)
Route::middleware('auth')->get('events/{event}/rows/{rowId}/seats', [BookingController::class, 'getRowSeats']);
