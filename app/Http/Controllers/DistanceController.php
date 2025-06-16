<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Distance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DistanceController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->date)->toDateString();
        $mapboxToken = config('services.mapbox.access_token');

        if (!$mapboxToken) {
            return response()->json(['error' => 'Mapbox token is missing'], 400);
        }

        // Get activities for the date, ordered by datetime
        $activities = Activity::whereDate('datetime', $date)
            ->orderBy('datetime')
            ->get()
            ->keyBy('activity');

        $segments = [
            'home_to_depot' => ['Left Home', 'Arrive Depot'],
            'leave_depot_to_first_drop' => ['Leave Depot', 'First Drop'],
            'last_drop_to_home' => ['Last Drop', 'Arrive Home'],
        ];

        $results = [];
        $errors = [];

        foreach ($segments as $segment => [$fromActivity, $toActivity]) {
            if (!isset($activities[$fromActivity]) || !isset($activities[$toActivity])) {
                $errors[] = "Missing activities for segment '$segment' on $date";
                continue;
            }

            $from = $activities[$fromActivity];
            $to = $activities[$toActivity];

            // Call Mapbox Directions API
            $response = Http::get('https://api.mapbox.com/directions/v5/mapbox/driving/' . 
                "{$from->longitude},{$from->latitude};{$to->longitude},{$to->latitude}", [
                'access_token' => $mapboxToken,
                'geometries' => 'geojson',
            ]);

            if ($response->failed()) {
                $errors[] = "Failed to calculate distance for '$segment' on $date";
                continue;
            }

            $data = $response->json();
            if (empty($data['routes'][0]['distance'])) {
                $errors[] = "No route found for '$segment' on $date";
                continue;
            }

            $distance = $data['routes'][0]['distance'] / 1000; // Convert meters to kilometers

            // Store or update distance
            Distance::updateOrCreate(
                ['date' => $date, 'segment' => $segment],
                [
                    'distance' => $distance,
                    'activity_from_id' => $from->id,
                    'activity_to_id' => $to->id,
                ]
            );

            $results[$segment] = $distance;
        }

        if (empty($results) && !empty($errors)) {
            return response()->json(['error' => implode('; ', $errors)], 400);
        }

        return response()->json([
            'distances' => $results,
            'errors' => $errors,
        ]);
    }
}