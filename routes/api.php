<?php

use App\Http\Controllers\Api\Admin\BillingAdminController;
use App\Http\Controllers\Api\Admin\InvoiceAdminController;
use App\Http\Controllers\Api\AdminMetricsController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BillingInvoiceController;
use App\Http\Controllers\Api\BillingMeController;
use App\Http\Controllers\Api\BillingSeatsController;
use App\Http\Controllers\Api\BillingUpgradeController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\Public\PublicBookingController;

Route::get('/health', fn () => response()->json(['ok' => true]));

// AUTH
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// PRIVATE (Dashboard)
Route::middleware('auth:sanctum','ensure.billable')->group(function () {

    // staff
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::patch('/staff/{user}/deactivate', [StaffController::class, 'deactivate']);
    Route::patch('/staff/{user}/activate', [StaffController::class, 'activate']);

    // services
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

    // bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::patch('/bookings/{booking}/done', [BookingController::class, 'done']);
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
    Route::patch('/bookings/{booking}/time', [BookingController::class, 'updateTime']);
// Owner/Manager
    Route::get('/billing/invoices', [BillingInvoiceController::class, 'index']);
    Route::post('/billing/upgrade-request', [BillingInvoiceController::class, 'requestUpgrade']);
    Route::post('/billing/invoices/{invoice}/cancel', [BillingInvoiceController::class, 'cancel']);
    //calendar
    Route::get('/calendar', [CalendarController::class, 'index']);

    //stats
    Route::get('/stats', [StatsController::class, 'index']);

    Route::get('/dashboard', [DashboardController::class, 'show']);

    Route::get('/analytics', [AnalyticsController::class, 'summary']);
});



Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/billing/me', [BillingMeController::class, 'show']);

    Route::get('/plans', [PlanController::class, 'index']);

    Route::post('/billing/upgrade', [BillingUpgradeController::class, 'upgrade']);

    Route::get('/billing/seats', [BillingSeatsController::class, 'show']);
    Route::patch('/salons/{salon}/suspend', [BillingAdminController::class, 'suspend']);
    Route::patch('/salons/{salon}/restore', [BillingAdminController::class, 'restore']);
    Route::patch('/salons/{salon}/plan', [BillingAdminController::class, 'changePlan']);
    Route::patch('/salons/{salon}/trial', [BillingAdminController::class, 'extendTrial']);
    Route::get('/admin/mrr', [AdminMetricsController::class, 'mrr']);
});
Route::get('/availability', [AvailabilityController::class, 'day']);



// Super Admin
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/invoices', [InvoiceAdminController::class, 'index']);
    Route::patch('/invoices/{invoice}/approve', [InvoiceAdminController::class, 'approve']);
    Route::patch('/invoices/{invoice}/reject', [InvoiceAdminController::class, 'reject']);
});

// PUBLIC (Guest)
Route::prefix('public')->group(function () {
    Route::get('/salons/{slug}', [PublicBookingController::class, 'salon']);
    Route::get('/salons/{slug}/services', [PublicBookingController::class, 'services']);
    Route::get('/salons/{slug}/staff', [PublicBookingController::class, 'staff']);

    Route::get('/salons/{slug}/availability', [PublicBookingController::class, 'availability']);

    Route::post('/salons/{slug}/bookings', [PublicBookingController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/bookings/{code}', [PublicBookingController::class, 'show']);
    Route::post('/bookings/{code}/cancel', [PublicBookingController::class, 'cancel'])->middleware('throttle:20,1');
});
