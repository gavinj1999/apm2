<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\DashboardController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');



Route::middleware('auth')->group(function () {
    Route::resource('periods', PeriodController::class)->except(['show']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/manifests', [ManifestController::class, 'store'])->name('manifests.store');
    Route::get('/manifests/{id}', [ManifestController::class, 'getById'])->name('manifests.get-by-id'); // New: Fetch manifest by ID
    Route::put('/manifests/{id}', [ManifestController::class, 'updateById'])->name('manifests.update-by-id'); // New: Update manifest by ID
    Route::delete('/manifests/{id}', [ManifestController::class, 'deleteById'])->name('manifests.delete-by-id'); // New: Delete manifest by ID
});

Route::middleware(['auth'])->group(function () {
    Route::get('/manifests', [ManifestController::class, 'index'])->name('manifests.index');
    Route::get('/manifests/create', [ManifestController::class, 'create'])->name('manifests.create');
    Route::post('/manifests', [ManifestController::class, 'store'])->name('manifests.store');
});


Route::middleware(['auth'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');


require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
