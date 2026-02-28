<?php

use App\Http\Controllers\Api\CalendarBlockController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContactRequestController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StaffController;

use App\Http\Controllers\Api\BillingInvoiceController;
use App\Http\Controllers\Api\BillingMeController;
use App\Http\Controllers\Api\BillingSeatsController;
use App\Http\Controllers\Api\BillingUpgradeController;

use App\Http\Controllers\Api\Public\PublicBookingController;

use App\Http\Controllers\Api\BusinessOnboardingController;
use App\Http\Controllers\Api\BusinessSettingsController;

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\BusinessManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\LogController;

use App\Http\Controllers\Api\Admin\BillingAdminController;
use App\Http\Controllers\Api\Admin\InvoiceAdminController;
use App\Http\Controllers\Api\AdminMetricsController;

// ✅ IMPORTANT: split Plan controllers
use App\Http\Controllers\Api\PlanController as PublicPlanController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', fn () => response()->json(['ok' => true]));

/*
|--------------------------------------------------------------------------
| Public: Booking pages (Guest)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::get('/businesses/{slug}', [PublicBookingController::class, 'business']);
    Route::get('/businesses/{slug}/services', [PublicBookingController::class, 'services']);
    Route::get('/businesses/{slug}/staff', [PublicBookingController::class, 'staff']);
    Route::get('/businesses/{slug}/availability', [PublicBookingController::class, 'availability']);
    Route::post('/businesses/{slug}/bookings', [PublicBookingController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/bookings/{code}', [PublicBookingController::class, 'show']);
    Route::post('/bookings/{code}/verify', [PublicBookingController::class, 'verifyPhone'])->middleware('throttle:20,1');
    Route::post('/bookings/{code}/cancel', [PublicBookingController::class, 'cancel'])->middleware('throttle:20,1');
});

/*
|--------------------------------------------------------------------------
| Public: Availability (Guest)
|--------------------------------------------------------------------------
*/
Route::get('/availability', [AvailabilityController::class, 'availability']);

/*
|--------------------------------------------------------------------------
| ✅ Public Plans for Landing (Guest)  <-- սա է /api/plans?business_type=beauty
|--------------------------------------------------------------------------
*/
Route::get('/plans', [PublicPlanController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected Business Routes (with billing check)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'ensure.billable'])->group(function () {
    // Staff
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store'])->middleware('ensure.seat');
    Route::patch('/staff/{user}/deactivate', [StaffController::class, 'deactivate']);
    Route::patch('/staff/{user}/activate', [StaffController::class, 'activate'])->middleware('ensure.seat');

    // Services
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::patch('/bookings/{booking}/done', [BookingController::class, 'done']);
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
    Route::patch('/bookings/{booking}/time', [BookingController::class, 'updateTime']);

    // Billing (Owner/Manager)
    Route::get('/billing/invoices', [BillingInvoiceController::class, 'index']);
    Route::post('/billing/upgrade-request', [BillingInvoiceController::class, 'requestUpgrade']);
    Route::post('/billing/invoices/{invoice}/cancel', [BillingInvoiceController::class, 'cancel']);

    // Calendar
    Route::get('/calendar', [CalendarController::class, 'index']);


    Route::get('/calendar/blocks', [CalendarBlockController::class, 'index']);
    Route::post('/calendar/blocks', [CalendarBlockController::class, 'store']);
    Route::delete('/calendar/blocks/{block}', [CalendarBlockController::class, 'destroy']);
    // Stats
    Route::get('/stats', [StatsController::class, 'index']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'show']);

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'summary'])->middleware('ensure.feature:analytics');

    // Rooms
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{room}', [RoomController::class, 'update']);
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::put('/clients/{client}', [ClientController::class, 'update']);
    Route::get('/clients/{client}/bookings', [ClientController::class, 'bookings']);
});

/*
|--------------------------------------------------------------------------
| Onboarding
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->post('/business/complete-onboarding', [BusinessOnboardingController::class, 'complete']);

/*
|--------------------------------------------------------------------------
| Business Settings
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/business/settings', [BusinessSettingsController::class, 'show']);
    Route::patch('/business/settings', [BusinessSettingsController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Schedule
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/schedule', [ScheduleController::class, 'show']);
    Route::put('/schedule', [ScheduleController::class, 'update']);
    Route::get('/staff/{user}/schedule', [ScheduleController::class, 'showStaff']);
    Route::put('/staff/{user}/schedule', [ScheduleController::class, 'updateStaff']);
    Route::get('/exceptions', [ScheduleController::class, 'listExceptions']);
    Route::post('/exceptions', [ScheduleController::class, 'createException']);
    Route::delete('/exceptions/{id}', [ScheduleController::class, 'deleteException']);
});

/*
|--------------------------------------------------------------------------
| Analytics (Detailed)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/revenue', [AnalyticsController::class, 'revenue']);
    Route::get('/analytics/services', [AnalyticsController::class, 'services']);
    Route::get('/analytics/staff', [AnalyticsController::class, 'staff']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (for business owners) - NOT Super Admin panel
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/billing/me', [BillingMeController::class, 'show']);
    Route::get('/plans', [PublicPlanController::class, 'index']); // եթե owner-ը պետք է տեսնի public plans
    Route::post('/billing/upgrade', [BillingUpgradeController::class, 'upgrade']);
    Route::get('/billing/seats', [BillingSeatsController::class, 'show']);

    // Super Admin: Business Management endpoints (API)
    Route::patch('/businesses/{business}/suspend', [BillingAdminController::class, 'suspend']);
    Route::patch('/businesses/{business}/restore', [BillingAdminController::class, 'restore']);
    Route::patch('/businesses/{business}/plan', [BillingAdminController::class, 'changePlan']);
    Route::patch('/businesses/{business}/trial', [BillingAdminController::class, 'extendTrial']);

    // Metrics
    Route::get('/mrr', [AdminMetricsController::class, 'mrr']);

    // Contact Requests
    Route::get('/contact-requests', [ContactRequestController::class, 'index']);
    Route::patch('/contact-requests/{contactRequest}/read', [ContactRequestController::class, 'markAsRead']);

    // Invoices (Super Admin)
    Route::get('/invoices', [InvoiceAdminController::class, 'index']);
    Route::patch('/invoices/{invoice}/approve', [InvoiceAdminController::class, 'approve']);
    Route::patch('/invoices/{invoice}/reject', [InvoiceAdminController::class, 'reject']);
});

/*
|--------------------------------------------------------------------------
| Super Admin Panel Auth (public login)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| ✅ Super Admin Panel Protected Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Auth
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/analytics', [AdminDashboardController::class, 'analytics']);

    // Business Management
    Route::get('/businesses', [BusinessManagementController::class, 'index']);
    Route::get('/businesses/{business}', [BusinessManagementController::class, 'show']);
    Route::post('/businesses/{business}/suspend', [BusinessManagementController::class, 'suspend'])->middleware('admin:super_admin');
    Route::post('/businesses/{business}/restore', [BusinessManagementController::class, 'restore'])->middleware('admin:super_admin');

    // User Management
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::get('/users/{user}', [UserManagementController::class, 'show']);
    Route::patch('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive']);

    // Admin Management (Super Admin only)
    Route::apiResource('/admins', AdminManagementController::class)->middleware('admin:super_admin');

    // Logs (Super Admin only)
    Route::get('/logs', [LogController::class, 'index'])->middleware('admin:super_admin');
    Route::get('/logs/{id}', [LogController::class, 'show'])->middleware('admin:super_admin');
    Route::get('/logs/admin/{adminId}', [LogController::class, 'adminLogs'])->middleware('admin:super_admin');

    // Analytics (Super Admin only)
    Route::get('/analytics/dashboard', [AdminAnalyticsController::class, 'dashboard'])->middleware('admin:super_admin');
    Route::get('/analytics/businesses', [AdminAnalyticsController::class, 'businesses'])->middleware('admin:super_admin');
    Route::get('/analytics/revenue', [AdminAnalyticsController::class, 'revenue'])->middleware('admin:super_admin');
    Route::post('/analytics/export/businesses', [AdminAnalyticsController::class, 'exportBusinesses'])->middleware('admin:super_admin');
    Route::post('/analytics/export/revenue', [AdminAnalyticsController::class, 'exportRevenue'])->middleware('admin:super_admin');

    // ✅ Plans (Super Admin only) - FIXED controller
    Route::apiResource('/plans', AdminPlanController::class)->middleware('admin:super_admin');
    Route::post('/plans/reorder', [AdminPlanController::class, 'reorder'])->middleware('admin:super_admin');
    Route::patch('/plans/{plan}/toggle-active', [AdminPlanController::class, 'toggleActive'])->middleware('admin:super_admin');
    Route::patch('/plans/{plan}/toggle-visible', [AdminPlanController::class, 'toggleVisible'])->middleware('admin:super_admin');
});
Route::get('/public/plans', [PublicPlanController::class, 'index']);
