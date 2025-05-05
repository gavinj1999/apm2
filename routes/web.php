<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoundPricingController;
use App\Http\Controllers\NMWCalculatorController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ParcelTypeController;
use App\Http\Controllers\ServiceProfileController;
use App\Http\Controllers\LocationController;



use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');



Route::middleware('auth')->group(function () {
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
    Route::patch('/parcel-types/sort', [ParcelTypeController::class, 'updateSortOrder'])->name('parcel-types.sort');

    Route::resource('parcel-types', ParcelTypeController::class)->except(['create', 'edit', 'show']);

    Route::get('/service-profile', [ServiceProfileController::class, 'index'])->name('service-profile');
    Route::post('/service-profile', [ServiceProfileController::class, 'store'])->name('service-profile.store');
    Route::get('/service-profile/{id}/edit', [ServiceProfileController::class, 'edit'])->name('service-profile.edit');
    Route::put('/service-profile/{id}', [ServiceProfileController::class, 'update'])->name('service-profile.update');
    Route::delete('/service-profile/{id}', [ServiceProfileController::class, 'destroy'])->name('service-profile.destroy');



});





require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
