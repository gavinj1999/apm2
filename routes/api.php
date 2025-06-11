<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\TrafficController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\WorkLocationController;
use App\Http\Controllers\Api\ActivityController;

Route::get('/user', function (Request $request) {
    return $request->user();

})->middleware('auth:sanctum');

Route::post('/manifests', [ManifestController::class, 'store'])->middleware('auth:sanctum');
Route::get('/weather', [WeatherController::class, 'getWeather']);
Route::get('/traffic', [TrafficController::class, 'getTraffic']);

Route::middleware('auth:web')->group(function () {
    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::put('/locations/{location}', [LocationController::class, 'update']);
    Route::delete('/locations/{location}', [LocationController::class, 'destroy']);

    Route::get('/work-locations', [WorkLocationController::class, 'index']);
    Route::post('/work-locations', [WorkLocationController::class, 'store']);
});

Route::post('/activity', [ActivityController::class, 'store']);
Route::get('/activities', [ActivityController::class, 'index']);



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::put('/activity/{activity}', [ActivityController::class, 'update']);
    Route::patch('/activity/{activity}', [ActivityController::class, 'patch']);
    Route::delete('/activity/{activity}', [ActivityController::class, 'destroy']);
});