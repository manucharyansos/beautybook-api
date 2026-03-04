<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\Public\PublicBookingController;

use App\Http\Controllers\Api\FeaturesController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\BusinessOnboardingController;
use App\Http\Controllers\Api\BusinessSettingsController;

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CalendarBlockController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\GiftCardController;

use App\Http\Controllers\Api\BillingInvoiceController;

use App\Http\Controllers\Api\PlanController as PublicPlanController;

use App\Http\Controllers\Api\ContactRequestController;

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

use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\BillingSubscriptionController;

/*
|--------------------------------------------------------------------------
| Health
|--------------------------------------------------------------------------
*/
Route::get('/health', fn () => response()->json(['ok' => true]));

/*
|--------------------------------------------------------------------------
| Public Booking (Guest)
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
| Public Availability (Guest)
|--------------------------------------------------------------------------
*/
Route::get('/availability', [AvailabilityController::class, 'availability']);

/*
|--------------------------------------------------------------------------
| Public Plans (Landing)
|--------------------------------------------------------------------------
*/
Route::get('/plans', [PublicPlanController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Password reset (public)
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| ✅ Protected Business Routes
| - /features: available right after login (even before onboarding)
| - onboarding routes: allowed BEFORE ensure.onboarded
| - main app routes: require ensure.onboarded + ensure.billable
|--------------------------------------------------------------------------
*/

/**
 * ✅ Available immediately after login (even before onboarding)
 */
Route::middleware(['auth:sanctum', 'ensure.business'])->group(function () {
    Route::get('/features', [FeaturesController::class, 'index'])->name('features.index');
       Route::get('/business-types', [BusinessTypeController::class, 'show'])
        ->middleware('role:owner,manager')
        ->name('business.types.show');
});

/**
 * ✅ ONBOARDING FLOW (allowed BEFORE ensure.onboarded)
 * works while business->is_onboarding_completed = false
 */
Route::middleware(['auth:sanctum', 'ensure.business', 'role:owner,manager'])->group(function () {

    // Step 0: Create first service
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');

    // Step 1: Create first staff (seat limit applies)
    Route::post('/staff', [StaffController::class, 'store'])
        ->middleware('ensure.seat')
        ->name('staff.store');

    // Step 2: Save work hours/settings (PATCH)
    Route::patch('/business/settings', [BusinessSettingsController::class, 'update'])
        ->name('business.settings.update');

    // Finish onboarding
    Route::post('/business/complete-onboarding', [BusinessOnboardingController::class, 'complete'])
        ->name('business.complete-onboarding');
});

/**
 * ✅ MAIN APP (only AFTER onboarding is completed + business is billable)
 */
Route::middleware(['auth:sanctum', 'ensure.business', 'ensure.onboarded', 'ensure.billable'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Business Settings (Owner/Manager)  ✅ GET + PATCH
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/business/settings', [BusinessSettingsController::class, 'show'])->name('business.settings.show');
        Route::patch('/business/settings', [BusinessSettingsController::class, 'update'])->name('business.settings.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Staff (Owner/Manager)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::patch('/staff/{user}/deactivate', [StaffController::class, 'deactivate'])->name('staff.deactivate');
        Route::patch('/staff/{user}/activate', [StaffController::class, 'activate'])
            ->middleware('ensure.seat')
            ->name('staff.activate');
    });

    /*
    |--------------------------------------------------------------------------
    | Services (Owner/Manager) - index/update/delete AFTER onboarding
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::patch('/bookings/{booking}/done', [BookingController::class, 'done'])->name('bookings.done');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::patch('/bookings/{booking}/time', [BookingController::class, 'updateTime'])->name('bookings.time');
    Route::post('/bookings/multi', [BookingController::class, 'storeMulti'])->name('bookings.storeMulti');
    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    */
   Route::middleware(['auth:sanctum','ensure.business','ensure.onboarded','role:owner,manager'])->group(function () {
    Route::get('/billing/invoices', [BillingInvoiceController::class, 'index'])->name('billing.invoices.index');
    Route::post('/billing/upgrade-request', [BillingInvoiceController::class, 'requestUpgrade'])->name('billing.upgrade.request');
    Route::post('/billing/invoices/{invoice}/cancel', [BillingInvoiceController::class, 'cancel'])->name('billing.invoices.cancel');
});
/*
    |--------------------------------------------------------------------------
    | Calendar
    |--------------------------------------------------------------------------
    */
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    Route::middleware(['ensure.feature:blocks', 'role:owner,manager'])->group(function () {
        Route::get('/calendar/blocks', [CalendarBlockController::class, 'index'])->name('calendar.blocks.index');
        Route::post('/calendar/blocks', [CalendarBlockController::class, 'store'])->name('calendar.blocks.store');
        Route::delete('/calendar/blocks/{block}', [CalendarBlockController::class, 'destroy'])->name('calendar.blocks.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Schedule  ✅ required by frontend (/api/schedule)
    |--------------------------------------------------------------------------
    */
    Route::get('/schedule', [ScheduleController::class, 'show'])->name('schedule.show');

    Route::put('/schedule', [ScheduleController::class, 'update'])
        ->middleware('role:owner,manager')
        ->name('schedule.update');

    Route::get('/staff/{user}/schedule', [ScheduleController::class, 'showStaff'])->name('schedule.staff.show');

    Route::put('/staff/{user}/schedule', [ScheduleController::class, 'updateStaff'])
        ->middleware('role:owner,manager')
        ->name('schedule.staff.update');

    Route::get('/exceptions', [ScheduleController::class, 'listExceptions'])->name('schedule.exceptions.index');

    Route::post('/exceptions', [ScheduleController::class, 'createException'])
        ->middleware('role:owner,manager')
        ->name('schedule.exceptions.store');

    Route::delete('/exceptions/{id}', [ScheduleController::class, 'deleteException'])
        ->middleware('role:owner,manager')
        ->name('schedule.exceptions.destroy');

    /*
    |--------------------------------------------------------------------------
    | Stats / Dashboard
    |--------------------------------------------------------------------------
    */
    Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard.show');

    /*
    |--------------------------------------------------------------------------
    | Analytics (Summary + Detailed) ✅ matches your AnalyticsController
    |--------------------------------------------------------------------------
    */
    Route::middleware('ensure.feature:analytics')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'summary'])->name('analytics.summary');

        Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
        Route::get('/analytics/revenue', [AnalyticsController::class, 'revenue'])->name('analytics.revenue');
        Route::get('/analytics/services', [AnalyticsController::class, 'services'])->name('analytics.services');
        Route::get('/analytics/staff', [AnalyticsController::class, 'staff'])->name('analytics.staff');
    });

    /*
    |--------------------------------------------------------------------------
    | Rooms
    |--------------------------------------------------------------------------
    */
    Route::middleware(['ensure.feature:rooms', 'role:owner,manager'])->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Clients
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::get('/clients/{client}/bookings', [ClientController::class, 'bookings'])->name('clients.bookings');
    });

    /*
    |--------------------------------------------------------------------------
    | Loyalty
    |--------------------------------------------------------------------------
    */
    Route::middleware(['ensure.feature:loyalty', 'role:owner,manager'])->group(function () {
        Route::get('/loyalty/program', [LoyaltyController::class, 'program'])->name('loyalty.program.show');
        Route::put('/loyalty/program', [LoyaltyController::class, 'updateProgram'])->name('loyalty.program.update');
        Route::get('/loyalty/clients', [LoyaltyController::class, 'clients'])->name('loyalty.clients.index');
        Route::post('/loyalty/clients/{client}/adjust', [LoyaltyController::class, 'adjust'])->name('loyalty.clients.adjust');
    });

    /*
    |--------------------------------------------------------------------------
    | Gift Cards
    |--------------------------------------------------------------------------
    */
    Route::middleware(['ensure.feature:gift_cards', 'role:owner,manager'])->group(function () {
        Route::get('/gift-cards', [GiftCardController::class, 'index'])->name('giftcards.index');
        Route::post('/gift-cards', [GiftCardController::class, 'store'])->name('giftcards.store');
        Route::get('/gift-cards/{giftCard}', [GiftCardController::class, 'show'])->name('giftcards.show');
        Route::put('/gift-cards/{giftCard}', [GiftCardController::class, 'update'])->name('giftcards.update');
        Route::patch('/gift-cards/{giftCard}/redeem', [GiftCardController::class, 'redeem'])->name('giftcards.redeem');
    });
});

/*
|--------------------------------------------------------------------------
| Admin Panel Auth (public login)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});


/*
|--------------------------------------------------------------------------
| ✅ Billing management (must work even when subscription is inactive)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'ensure.business', 'ensure.onboarded', 'role:owner,manager'])->group(function () {
    Route::post('/billing/pause', [BillingSubscriptionController::class, 'pause'])->name('billing.pause');
    Route::post('/billing/resume', [BillingSubscriptionController::class, 'resume'])->name('billing.resume');
    Route::post('/billing/cancel-subscription', [BillingSubscriptionController::class, 'cancel'])->name('billing.cancel');
});

/*
|--------------------------------------------------------------------------
| Super Admin Panel Protected Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);

    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/analytics', [AdminDashboardController::class, 'analytics']);

    Route::get('/businesses', [BusinessManagementController::class, 'index']);
    Route::get('/businesses/{business}', [BusinessManagementController::class, 'show']);
    Route::post('/businesses/{business}/suspend', [BusinessManagementController::class, 'suspend'])->middleware('admin:super_admin');
    Route::post('/businesses/{business}/restore', [BusinessManagementController::class, 'restore'])->middleware('admin:super_admin');

    Route::get('/users', [UserManagementController::class, 'index']);
    Route::get('/users/{user}', [UserManagementController::class, 'show']);
    Route::patch('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive']);

    Route::apiResource('/admins', AdminManagementController::class)->middleware('admin:super_admin');

    Route::get('/logs', [LogController::class, 'index'])->middleware('admin:super_admin');
    Route::get('/logs/{id}', [LogController::class, 'show'])->middleware('admin:super_admin');
    Route::get('/logs/admin/{adminId}', [LogController::class, 'adminLogs'])->middleware('admin:super_admin');

    Route::get('/analytics/dashboard', [AdminAnalyticsController::class, 'dashboard'])->middleware('admin:super_admin');
    Route::get('/analytics/businesses', [AdminAnalyticsController::class, 'businesses'])->middleware('admin:super_admin');
    Route::get('/analytics/revenue', [AdminAnalyticsController::class, 'revenue'])->middleware('admin:super_admin');
    Route::post('/analytics/export/businesses', [AdminAnalyticsController::class, 'exportBusinesses'])->middleware('admin:super_admin');
    Route::post('/analytics/export/revenue', [AdminAnalyticsController::class, 'exportRevenue'])->middleware('admin:super_admin');

    Route::apiResource('/plans', AdminPlanController::class)->middleware('admin:super_admin');
    Route::post('/plans/reorder', [AdminPlanController::class, 'reorder'])->middleware('admin:super_admin');
    Route::patch('/plans/{plan}/toggle-active', [AdminPlanController::class, 'toggleActive'])->middleware('admin:super_admin');
    Route::patch('/plans/{plan}/toggle-visible', [AdminPlanController::class, 'toggleVisible'])->middleware('admin:super_admin');
});
