<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ExceptionController;
use App\Http\Controllers\Admin\ExceptionDecisionController;
use App\Http\Controllers\Admin\HistoryController;
use App\Http\Controllers\Admin\HistoryExportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\ShipmentAssignmentController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShipmentExportController;
use App\Http\Controllers\Admin\ShipmentRescheduleController;
use App\Http\Controllers\Api\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Api\Client\IssueChoiceController as ClientApiIssueChoiceController;
use App\Http\Controllers\Api\Client\IssueController as ClientApiIssueController;
use App\Http\Controllers\Api\Client\LinkController as ClientLinkController;
use App\Http\Controllers\Api\Client\OrderController as ClientOrderController;
use App\Http\Controllers\Api\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Api\Client\ReplacementController as ClientApiReplacementController;
use App\Http\Controllers\Api\Client\ScheduleConfirmationController as ClientApiScheduleConfirmationController;
use App\Http\Controllers\Api\Client\ScheduleController as ClientApiScheduleController;
use App\Http\Controllers\Api\Client\ScheduleRescheduleController as ClientApiScheduleRescheduleController;
use App\Http\Controllers\Api\Client\TrackingController as ClientApiTrackingController;
use App\Http\Controllers\Api\Courier\AuthController as CourierAuthController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Client\EntryController;
use App\Http\Controllers\Client\IssueController;
use App\Http\Controllers\Client\ReplacementController;
use App\Http\Controllers\Client\ScheduleConfirmationController;
use App\Http\Controllers\Client\ScheduleController;
use App\Http\Controllers\Client\ScheduleRescheduleController;
use App\Http\Controllers\Client\TrackingController;
use App\Http\Controllers\Courier\AcceptTaskController;
use App\Http\Controllers\Courier\DeliveryController;
use App\Http\Controllers\Courier\IssueReportController;
use App\Http\Controllers\Courier\MarkArrivedController;
use App\Http\Controllers\Courier\ProfileController;
use App\Http\Controllers\Courier\ProofController;
use App\Http\Controllers\Courier\RejectTaskController;
use App\Http\Controllers\Courier\StartDeliveryController;
use App\Http\Controllers\Courier\TaskController;
use App\Http\Controllers\Courier\TaskDistanceController;
use App\Http\Controllers\Api\Internal\WhatsappLookupController;
use App\Http\Controllers\Media\MediaPreviewController;
use App\Http\Middleware\AuthenticateCourier;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Backend API endpoints for Anteraja Frozen.
| Handled as pure JSON responses (no Blade views or frontend touches).
|
*/

// Authentication
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('login', [SessionController::class, 'store'])->name('login');
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('me', [SessionController::class, 'me'])->name('me');
});

// Proof photos. MediaStorage::temporaryUrl cannot sign a local-disk URL, so it
// hands out this route instead; only the console renders those photos.
Route::get('media/preview', MediaPreviewController::class)
    ->middleware(['staff.role:ADMIN'])
    ->name('api.media.preview');

// Admin Panel API Routes (Protected by staff.role:ADMIN)
Route::prefix('admin')->name('api.admin.')->middleware(['staff.role:ADMIN'])->group(function () {
    // 1. Shipments
    Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::get('shipments/export', ShipmentExportController::class)->name('shipments.export');
    Route::get('shipments/{package:tracking_number}', [ShipmentController::class, 'show'])->name('shipments.show');
    Route::post('shipments/{package:tracking_number}/assign', ShipmentAssignmentController::class)->name('shipments.assign');
    Route::post('shipments/{package:tracking_number}/reschedule', ShipmentRescheduleController::class)->name('shipments.reschedule');

    // 2. Exceptions / Kendala
    Route::get('exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
    Route::get('exceptions/{package:tracking_number}', [ExceptionController::class, 'show'])->name('exceptions.show');
    Route::post('exceptions/{package:tracking_number}/decision', ExceptionDecisionController::class)->name('exceptions.decision');

    // 3. Reports / Laporan
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', ReportExportController::class)->name('reports.export');

    // 4. History / Riwayat
    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('history/export', HistoryExportController::class)->name('history.export');
});

// Courier Mobile Auth (Staff email + password -> Sanctum token)
Route::prefix('courier/auth')->name('api.courier.auth.')
    ->middleware(['force.json', 'throttle:courier-auth'])
    ->group(function () {
        Route::post('login', [CourierAuthController::class, 'login'])->name('login');
    });

// Courier Mobile API Routes (Protected by staff.role:COURIER)
Route::prefix('courier')->name('api.courier.')->middleware([AuthenticateCourier::class, 'abilities:courier', 'staff.role:COURIER'])->group(function () {
    Route::post('auth/logout', [CourierAuthController::class, 'logout'])->name('auth.logout');

    // 1. Tasks
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/{package:tracking_number}/distance', TaskDistanceController::class)->name('tasks.distance');
    Route::post('tasks/{package:tracking_number}/accept', AcceptTaskController::class)->name('tasks.accept');
    Route::post('tasks/{package:tracking_number}/reject', RejectTaskController::class)->name('tasks.reject');

    // 2. Deliveries
    Route::get('deliveries/{package:tracking_number}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('deliveries/{package:tracking_number}/depart', StartDeliveryController::class)->name('deliveries.depart');
    Route::post('deliveries/{package:tracking_number}/arrive', MarkArrivedController::class)->name('deliveries.arrive');

    // 3. Proof of Delivery
    Route::get('deliveries/{package:tracking_number}/proof', [ProofController::class, 'create'])->name('proof.create');
    Route::post('deliveries/{package:tracking_number}/proof', [ProofController::class, 'store'])->name('proof.store');

    // 4. Field Issue Reporting
    Route::get('deliveries/{package:tracking_number}/issue', [IssueReportController::class, 'create'])->name('issues.create');
    Route::post('deliveries/{package:tracking_number}/issue', [IssueReportController::class, 'store'])->name('issues.store');

    // 5. History & Profile
    Route::get('history', [App\Http\Controllers\Courier\HistoryController::class, 'index'])->name('history.index');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
});

// 1) Public Client Auth (Phone + Password Login)
Route::prefix('client/auth')->name('api.client.auth.')
    ->middleware(['force.json', 'client.headers', 'throttle:client-auth'])
    ->group(function () {
        Route::post('login', [ClientAuthController::class, 'login'])->name('login');
    });

// 2) Authenticated Client Mobile API (Sanctum with ability "client")
Route::prefix('client')->name('api.client.')
    ->middleware(['force.json', 'client.headers', 'auth:sanctum', 'abilities:client', 'client.buyer', 'throttle:60,1'])
    ->group(function () {
        Route::post('auth/logout', [ClientAuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [ClientProfileController::class, 'show'])->name('me');

        Route::get('orders', [ClientOrderController::class, 'index'])->name('orders.index');
        Route::prefix('orders/{tracking_number}')->name('orders.')->middleware('client.owned')->group(function () {
            Route::get('/', [ClientOrderController::class, 'show'])->name('show');
            Route::get('tracking', [ClientApiTrackingController::class, 'show'])->name('tracking.show');
            Route::get('schedule', [ClientApiScheduleController::class, 'show'])->name('schedule.show');
            Route::post('schedule/confirm', ClientApiScheduleConfirmationController::class)->name('schedule.confirm');
            Route::post('schedule/reschedule', ClientApiScheduleRescheduleController::class)->name('schedule.reschedule');
            Route::get('issue', [ClientApiIssueController::class, 'show'])->name('issue.show');
            Route::post('issue/resend', ClientApiReplacementController::class)->name('issue.resend');
        });
    });

// 3) Guest / WhatsApp Deep-Link Routes (Token-Based)
Route::prefix('guest/{token}')->where(['token' => '[A-Fa-f0-9]{32,128}'])->name('api.guest.')
    ->middleware(['force.json', 'client.headers', 'throttle:60,1', 'client.package'])
    ->group(function () {
        Route::post('claim', [ClientAuthController::class, 'claim'])->name('claim');
        Route::get('/', [ClientLinkController::class, 'resolve'])->name('resolve');
        Route::get('tracking', [ClientApiTrackingController::class, 'show'])->name('tracking.show');
        Route::get('schedule', [ClientApiScheduleController::class, 'show'])->name('schedule.show');
        Route::post('schedule/confirm', ClientApiScheduleConfirmationController::class)->name('schedule.confirm');
        Route::post('schedule/reschedule', ClientApiScheduleRescheduleController::class)->name('schedule.reschedule');
        Route::get('issue', [ClientApiIssueController::class, 'show'])->name('issue.show');
        Route::post('issue/choice', ClientApiIssueChoiceController::class)->name('issue.choice');
    });

// 4) Client Mobile API Legacy Token-Based Routes (Deep-Link compatibility)
Route::prefix('client/{token}')->where(['token' => '[A-Fa-f0-9]{32,128}'])->name('api.client.')
    ->middleware(['client.headers', 'throttle:60,1', 'client.package'])
    ->group(function () {
        Route::post('claim', [ClientAuthController::class, 'claim'])->name('claim');
        Route::get('/', EntryController::class)->name('entry');
        Route::get('tracking', [TrackingController::class, 'show'])->name('tracking.show');
        Route::get('schedule', [ScheduleController::class, 'show'])->name('schedule.show');
        Route::post('schedule/confirm', ScheduleConfirmationController::class)->name('schedule.confirm');
        Route::post('schedule/reschedule', ScheduleRescheduleController::class)->name('schedule.reschedule');
        Route::get('issue', [IssueController::class, 'show'])->name('issue.show');
        Route::post('issue/choice', ClientApiIssueChoiceController::class)->name('issue.choice');
        Route::post('issue/resend', ReplacementController::class)->name('issue.resend');
    });

// Internal automation hook. n8n calls this to resolve a tracking number a
// customer typed on WhatsApp, so the inbound flow can attach the conversation
// to that package. Guarded by the shared X-Anteraja-Token secret.
Route::prefix('internal')->name('api.internal.')
    ->middleware(['force.json', 'internal.token', 'throttle:60,1'])
    ->group(function () {
        Route::post('whatsapp/lookup', WhatsappLookupController::class)->name('whatsapp.lookup');
    });

// OpenAPI / Swagger Documentation
// 1. Tampilan Visual Swagger UI: http://127.0.0.1:8000/api/docs
Route::get('docs', function () {
    abort_if(app()->isProduction(), 404);

    return response()->file(base_path('docs/index.html'), [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);
})->name('api.docs.ui');

// 2. OpenAPI Spec JSON: http://127.0.0.1:8000/api/docs/openapi.json
Route::get('docs/openapi.json', function () {
    abort_if(app()->isProduction(), 404);

    return response()->file(base_path('docs/openapi.json'), [
        'Content-Type' => 'application/json',
    ]);
})->name('api.docs.json');

// 3. OpenAPI Spec YAML: http://127.0.0.1:8000/api/docs/openapi.yaml
Route::get('docs/openapi.yaml', function () {
    abort_if(app()->isProduction(), 404);

    return response()->file(base_path('docs/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
})->name('api.docs.yaml');
