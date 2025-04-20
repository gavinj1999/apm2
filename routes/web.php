<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ReportController;
use App\Https\Controllers\ManifestController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('periods', PeriodController::class)->except(['show']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});





require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
