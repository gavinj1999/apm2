<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoundPricingController;

use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');



Route::middleware('auth')->group(function () {
    Route::resource('periods', PeriodController::class)->except(['show']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [ManifestController::class, 'index'])->middleware('auth')->name('dashboard');
    Route::post('/manifests', [ManifestController::class, 'store'])->middleware('auth')->name('manifests.store');
    Route::get('/manifests/{id}', [ManifestController::class, 'show'])->middleware('auth')->name('manifests.show');
    Route::put('/manifests/{id}', [ManifestController::class, 'update'])->middleware('auth')->name('manifests.update-by-id');
    Route::delete('/manifests/{id}', [ManifestController::class, 'destroy'])->middleware('auth')->name('manifests.destroy');

    Route::get('/prices', [RoundPricingController::class, 'index'])->name('prices.index');
    Route::post('/prices', [RoundPricingController::class, 'store'])->name('prices.store');
    Route::put('/prices/{id}', [RoundPricingController::class, 'update'])->name('prices.update');
    Route::delete('/prices/{id}', [RoundPricingController::class, 'destroy'])->name('prices.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/manifests', [ManifestController::class, 'index'])->name('manifests.index');
    Route::get('/manifests/create', [ManifestController::class, 'create'])->name('manifests.create');
    Route::post('/manifests', [ManifestController::class, 'store'])->name('manifests.store');
});



//Route::middleware(['auth'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');


require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
