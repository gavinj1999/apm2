<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActivityController extends Controller
{
    public function index()
    {
        return Inertia::render('Activities/Index', [
            'activities' => Activity::latest()->get()->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toISOString(),
                    'latitude' => (float) $activity->latitude,
                    'longitude' => (float) $activity->longitude,
                    'activity' => $activity->activity,
                ];
            }),
            'mapboxToken' => env('MAPBOX_ACCESS_TOKEN'),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|in:Left Home,Arrive Depot,Start Loading,Leave Depot,First Drop,Last Drop,Arrive Home',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::create($request->all());

        return response()->json([
            'data' => [
                'id' => $activity->id,
                'datetime' => $activity->datetime->toISOString(),
                'latitude' => (float) $activity->latitude,
                'longitude' => (float) $activity->longitude,
                'activity' => $activity->activity,
            ]
        ], 201);
    }

    public function update(Request $request, Activity $activity)
    {
        $validator = Validator::make($request->all(), [
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|in:Left Home,Arrive Depot,Start Loading,Leave Depot,First Drop,Last Drop,Arrive Home',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity->update($request->all());

        return response()->json([
            'data' => [
                'id' => $activity->id,
                'datetime' => $activity->datetime->toISOString(),
                'latitude' => (float) $activity->latitude,
                'longitude' => (float) $activity->longitude,
                'activity' => $activity->activity,
            ]
        ]);
    }

    public function destroy(Activity $activity)
    {
        $activity->delete();
        return response()->json(null, 204);
    }

    public function getActivityDates()
    {
        $dates = Activity::selectRaw('DATE(datetime) as date')
            ->distinct()
            ->pluck('date')
            ->map(function ($date) {
                return $date->format('Y-m-d');
            });

        return response()->json($dates);
    }
}