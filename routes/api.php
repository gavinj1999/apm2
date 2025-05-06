<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\TrafficController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\WorkLocationController;

Route::get('/user', function (Request $request) {
    return $request->user();

})->middleware('auth:sanctum');

Route::post('/manifests', [ManifestController::class, 'store'])->middleware('auth:sanctum');
Route::get('/weather', [WeatherController::class, 'getWeather']);
Route::get('/traffic', [TrafficController::class, 'getTraffic']);

Route::middleware(['web'])->group(function () {
    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::put('/locations/{id}', [LocationController::class, 'update']);
    Route::delete('/locations/{id}', [LocationController::class, 'destroy']);

    Route::get('/work-locations', [WorkLocationController::class, 'index']);
    Route::post('/work-locations', [WorkLocationController::class, 'store']);
});
