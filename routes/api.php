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

// Courier Mobile API Routes (Protected by staff.role:COURIER)
Route::prefix('courier')->name('api.courier.')->middleware(['staff.role:COURIER'])->group(function () {
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

// Client Mobile API Routes (No Auth, Token-Based)
Route::prefix('client/{token}')->name('api.client.')
    ->middleware(['client.headers', 'throttle:60,1', 'client.package'])
    ->group(function () {
        Route::get('/', EntryController::class)->name('entry');
        Route::get('tracking', [TrackingController::class, 'show'])->name('tracking.show');
        Route::get('schedule', [ScheduleController::class, 'show'])->name('schedule.show');
        Route::post('schedule/confirm', ScheduleConfirmationController::class)->name('schedule.confirm');
        Route::post('schedule/reschedule', ScheduleRescheduleController::class)->name('schedule.reschedule');
        Route::get('issue', [IssueController::class, 'show'])->name('issue.show');
        Route::post('issue/resend', ReplacementController::class)->name('issue.resend');
    });

// OpenAPI / Swagger Documentation Spec (Hanya aktif di non-production)
Route::get('docs/openapi.json', function () {
    abort_if(app()->isProduction(), 404);

    return response()->file(base_path('docs/openapi.json'), [
        'Content-Type' => 'application/json',
    ]);
})->name('api.docs.json');
