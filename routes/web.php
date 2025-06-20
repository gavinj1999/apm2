<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityTypeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoundPricingController;
use App\Http\Controllers\NMWCalculatorController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ParcelTypeController;
use App\Http\Controllers\ServiceProfileController;
use App\Http\Controllers\DistanceController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::put('/activity/{activity}', [ActivityController::class, 'update'])->name('activity.update');
    Route::patch('/activity/{activity}', [ActivityController::class, 'patch'])->name('activity.patch');
    Route::delete('/activity/{activity}', [ActivityController::class, 'destroy'])->name('activity.destroy');
    Route::post('/activity/{activity}/mark-as-correct', [ActivityController::class, 'markAsCorrect'])->name('activity.markAsCorrect');

    Route::get('/activity-types', [ActivityTypeController::class, 'index'])->name('activity-types.index');
    Route::post('/activity-types', [ActivityTypeController::class, 'store'])->name('activity-types.store');
    Route::put('/activity-types/{activityType}', [ActivityTypeController::class, 'update'])->name('activity-types.update');
    Route::delete('/activity-types/{activityType}', [ActivityTypeController::class, 'destroy'])->name('activity-types.destroy');

    Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
    Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');

    Route::resource('periods', PeriodController::class)->except(['show']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/manifests', [ManifestController::class, 'store'])->name('manifests.store');
    Route::get('/manifests/{id}', [ManifestController::class, 'show'])->name('manifests.show');
    Route::put('/manifests/{id}', [ManifestController::class, 'update'])->name('manifests.update');
    Route::delete('/manifests/{id}', [ManifestController::class, 'destroy'])->name('manifests.destroy');
    Route::get('/manifests', [ManifestController::class, 'index'])->name('manifests.index');
    Route::get('/manifests/create', [ManifestController::class, 'create'])->name('manifests.create');
    Route::get('/prices', [RoundPricingController::class, 'index'])->name('prices.index');
    Route::post('/prices', [RoundPricingController::class, 'store'])->name('prices.store');
    Route::put('/prices/{id}', [RoundPricingController::class, 'update'])->name('prices.update');
    Route::delete('/prices/{id}', [RoundPricingController::class, 'destroy'])->name('prices.destroy');
    Route::get('/dashboard/download-csv', [DashboardController::class, 'downloadCsv'])->name('dashboard.downloadCsv');
    Route::get('/nmw-calculator', [NMWCalculatorController::class, 'index'])->name('calculator.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
    Route::patch('/parcel-types/sort', [ParcelTypeController::class, 'updateSortOrder'])->name('parcel-types.sort');
    Route::resource('parcel-types', ParcelTypeController::class)->except(['create', 'edit', 'show']);
    Route::get('/service-profile', [ServiceProfileController::class, 'index'])->name('service-profile');
    Route::post('/service-profile', [ServiceProfileController::class, 'store'])->name('service-profile.store');
    Route::get('/service-profile/{id}/edit', [ServiceProfileController::class, 'edit'])->name('service-profile.edit');
    Route::put('/service-profile/{id}', [ServiceProfileController::class, 'update'])->name('service-profile.update');
    Route::delete('/service-profile/{id}', [ServiceProfileController::class, 'destroy'])->name('service-profile.destroy');
    Route::post('/distances/calculate', [ActivityController::class, 'calculateDistances']);
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';