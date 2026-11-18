<?php

use App\Http\Controllers\Api\V1\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Api\V1\Admin\DriverProfileApprovalController;
use App\Http\Controllers\Api\V1\Admin\DriverProfileController as AdminDriverProfileController;
use App\Http\Controllers\Api\V1\Admin\DriverProfileDocumentController;
use App\Http\Controllers\Api\V1\Admin\DriverProfileRejectionController;
use App\Http\Controllers\Api\V1\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Api\V1\Admin\RideReportController as AdminRideReportController;
use App\Http\Controllers\Api\V1\Admin\RideReportResolutionController;
use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AlertDestroyController;
use App\Http\Controllers\Api\V1\AlertReadController;
use App\Http\Controllers\Api\V1\AllAlertsDestroyController;
use App\Http\Controllers\Api\V1\AllAlertsReadController;
use App\Http\Controllers\Api\V1\Auth\LoginCodeController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CurrentRideController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\Driver\AvailabilityController;
use App\Http\Controllers\Api\V1\Driver\DriverDocumentController;
use App\Http\Controllers\Api\V1\Driver\DriverProfileController;
use App\Http\Controllers\Api\V1\Driver\DriverSubmissionController;
use App\Http\Controllers\Api\V1\Driver\LocationController;
use App\Http\Controllers\Api\V1\Driver\ReviewController;
use App\Http\Controllers\Api\V1\Driver\RideAcceptanceController;
use App\Http\Controllers\Api\V1\Driver\RideArrivalController;
use App\Http\Controllers\Api\V1\Driver\RideCompletionController;
use App\Http\Controllers\Api\V1\Driver\TripSummaryController;
use App\Http\Controllers\Api\V1\Driver\WaitingRideController;
use App\Http\Controllers\Api\V1\EmergencyAlertController;
use App\Http\Controllers\Api\V1\EmergencyContactController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NearbyDriverController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\RideCancellationController;
use App\Http\Controllers\Api\V1\RideController;
use App\Http\Controllers\Api\V1\RideDriverController;
use App\Http\Controllers\Api\V1\RideMessageController;
use App\Http\Controllers\Api\V1\RidePassengerController;
use App\Http\Controllers\Api\V1\RideRatingController;
use App\Http\Controllers\Api\V1\RideReportController;
use App\Http\Controllers\Api\V1\RideStartController;
use App\Http\Controllers\Api\V1\RouteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login-code', [LoginCodeController::class, 'store'])
            ->middleware('throttle:login-code')
            ->name('login-code.store');

        Route::post('token', [TokenController::class, 'store'])
            ->middleware('throttle:login-token')
            ->name('token.store');

        Route::delete('token', [TokenController::class, 'destroy'])
            ->middleware('auth:sanctum')
            ->name('token.destroy');
    });

    // A photo of the chat, with a link that is signed and lasts an hour, see `RideMessage::imageUrl()`.
    Route::get('rides/{ride}/messages/{message}/image', [RideMessageController::class, 'image'])
        ->middleware('signed:relative')
        ->whereNumber(['ride', 'message'])
        ->name('rides.messages.image');

    // The photo of the driver of a ride, with a link that is signed and lasts a while, see `Ride::driverPhotoUrl()`.
    Route::get('rides/{ride}/driver/photo', [RideDriverController::class, 'photo'])
        ->middleware('signed:relative')
        ->whereNumber('ride')
        ->name('rides.driver.photo');

    Route::get('me', [MeController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('me.show');

    Route::put('me', [MeController::class, 'update'])
        ->middleware('auth:sanctum')
        ->name('me.update');

    Route::delete('me', [MeController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('me.destroy');

    Route::middleware(['auth:sanctum', 'role:passenger,driver'])->group(function () {
        Route::get('places', [PlaceController::class, 'index'])->name('places.index');
        Route::get('route', [RouteController::class, 'show'])->middleware('throttle:60,1')->name('route.show');

        Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('alerts/read', [AllAlertsReadController::class, 'store'])->name('alerts.read-all.store');
        Route::delete('alerts', [AllAlertsDestroyController::class, 'destroy'])->name('alerts.destroy-all');
        Route::delete('alerts/{alert}', [AlertDestroyController::class, 'destroy'])->name('alerts.destroy');
        Route::post('alerts/{alert}/read', [AlertReadController::class, 'store'])->name('alerts.read.store');

        Route::get('emergency-contacts', [EmergencyContactController::class, 'index'])->name('emergency-contacts.index');
        Route::post('emergency-contacts', [EmergencyContactController::class, 'store'])->name('emergency-contacts.store');
        Route::delete('emergency-contacts/{contact}', [EmergencyContactController::class, 'destroy'])->whereNumber('contact')->name('emergency-contacts.destroy');
        Route::post('emergency-alert', [EmergencyAlertController::class, 'store'])->middleware('throttle:5,1')->name('emergency-alert.store');

        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');

        Route::get('chats', [ChatController::class, 'index'])->name('chats.index');

        Route::get('rides', [RideController::class, 'index'])->name('rides.index');
        Route::get('rides/current', [CurrentRideController::class, 'show'])->name('rides.current.show');
        Route::get('rides/{ride}', [RideController::class, 'show'])->whereNumber('ride')->name('rides.show');
        Route::post('rides/{ride}/cancellation', [RideCancellationController::class, 'store'])->whereNumber('ride')->name('rides.cancellation.store');
        Route::post('rides/{ride}/report', [RideReportController::class, 'store'])->whereNumber('ride')->name('rides.report.store');
        Route::post('rides/{ride}/start', [RideStartController::class, 'store'])->whereNumber('ride')->name('rides.start.store');
        Route::get('rides/{ride}/messages', [RideMessageController::class, 'index'])->whereNumber('ride')->name('rides.messages.index');
        Route::post('rides/{ride}/messages', [RideMessageController::class, 'store'])->middleware('throttle:30,1')->whereNumber('ride')->name('rides.messages.store');
    });

    Route::middleware(['auth:sanctum', 'role:passenger'])->group(function () {
        Route::get('drivers/nearby', [NearbyDriverController::class, 'index'])->name('drivers.nearby.index');
        Route::post('rides', [RideController::class, 'store'])->name('rides.store');
        Route::get('rides/{ride}/driver', [RideDriverController::class, 'show'])->whereNumber('ride')->name('rides.driver.show');
        Route::post('rides/{ride}/rating', [RideRatingController::class, 'store'])->whereNumber('ride')->name('rides.rating.store');
    });

    Route::middleware(['auth:sanctum', 'role:driver'])->prefix('driver')->name('driver.')->group(function () {
        Route::get('profile', [DriverProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [DriverProfileController::class, 'update'])->name('profile.update');

        Route::get('documents/{documentType}', [DriverDocumentController::class, 'show'])->name('documents.show');
        Route::post('documents/{documentType}', [DriverDocumentController::class, 'store'])->name('documents.store');

        Route::post('submission', [DriverSubmissionController::class, 'store'])->name('submission.store');

        Route::put('availability', [AvailabilityController::class, 'update'])->name('availability.update');
        Route::put('location', [LocationController::class, 'update'])->name('location.update');
        Route::get('waiting-rides', [WaitingRideController::class, 'index'])->name('waiting-rides.index');
        Route::get('rides/{ride}/passenger', [RidePassengerController::class, 'show'])->whereNumber('ride')->name('rides.passenger.show');
        Route::get('trip-summary', [TripSummaryController::class, 'show'])->name('trip-summary.show');
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');

        Route::post('rides/{ride}/acceptance', [RideAcceptanceController::class, 'store'])->whereNumber('ride')->name('rides.acceptance.store');
        Route::post('rides/{ride}/arrival', [RideArrivalController::class, 'store'])->whereNumber('ride')->name('rides.arrival.store');
        Route::post('rides/{ride}/completion', [RideCompletionController::class, 'store'])->whereNumber('ride')->name('rides.completion.store');
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('overview', [AdminOverviewController::class, 'show'])->name('overview.show');
        Route::get('drivers', [AdminDriverController::class, 'index'])->name('drivers.index');

        Route::get('driver-profiles', [AdminDriverProfileController::class, 'index'])->name('driver-profiles.index');
        Route::get('driver-profiles/{driverProfile}', [AdminDriverProfileController::class, 'show'])->name('driver-profiles.show');

        Route::get('driver-profiles/{driverProfile}/documents/{documentType}', [DriverProfileDocumentController::class, 'show'])
            ->name('driver-profiles.documents.show');

        Route::post('driver-profiles/{driverProfile}/approval', [DriverProfileApprovalController::class, 'store'])
            ->name('driver-profiles.approval.store');

        Route::get('ride-reports', [AdminRideReportController::class, 'index'])->name('ride-reports.index');
        Route::get('ride-reports/{rideReport}', [AdminRideReportController::class, 'show'])->whereNumber('rideReport')->name('ride-reports.show');
        Route::post('ride-reports/{rideReport}/resolution', [RideReportResolutionController::class, 'store'])
            ->whereNumber('rideReport')
            ->name('ride-reports.resolution.store');

        Route::post('driver-profiles/{driverProfile}/rejection', [DriverProfileRejectionController::class, 'store'])
            ->name('driver-profiles.rejection.store');
    });
});
