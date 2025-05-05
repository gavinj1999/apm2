<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\TrafficController;
use App\Http\Controllers\LocationController;

Route::get('/user', function (Request $request) {
    return $request->user();

})->middleware('auth:sanctum');

Route::post('/manifests', [ManifestController::class, 'store'])->middleware('auth:sanctum');
Route::get('/weather', [WeatherController::class, 'getWeather']);
Route::get('/traffic', [TrafficController::class, 'getTraffic']);

Route::middleware(['web'])->group(function () {
    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::delete('/locations/{id}', [LocationController::class, 'destroy']);
});
