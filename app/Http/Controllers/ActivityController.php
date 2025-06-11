<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $startOfWeek = Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        $activities = Activity::whereBetween('datetime', [$startOfWeek, $endOfWeek])
            ->latest()
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toISOString(),
                    'latitude' => (float) $activity->latitude,
                    'longitude' => (float) $activity->longitude,
                    'activity' => $activity->activity,
                    'is_manual' => $activity->is_manual,
                ];
            });

        $activityDates = Activity::select('datetime')
            ->distinct()
            ->pluck('datetime')
            ->map(function ($date) {
                return $date->format('Y-m-d');
            });

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
            'activityDates' => $activityDates,
            'mapboxToken' => env('MAPBOX_ACCESS_TOKEN'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|in:Left Home,Arrive Depot,Start Loading,Leave Depot,First Drop,Last Drop,Arrive Home',
            'is_manual' => 'sometimes|boolean',
        ]);

        $activity = Activity::create($validated + ['is_manual' => $request->input('is_manual', false)]);

        return redirect()->route('activities')->with('success', 'Activity created successfully');
    }

    public function update(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|in:Left Home,Arrive Depot,Start Loading,Leave Depot,First Drop,Last Drop,Arrive Home',
            'is_manual' => 'sometimes|boolean',
        ]);

        $activity->update($validated);

        return redirect()->route('activities')->with('success', 'Activity updated successfully');
    }

    public function patch(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'datetime' => 'sometimes|nullable|date',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'is_manual' => 'sometimes|boolean',
        ]);

        $activity->update(array_filter($validated));

        return redirect()->route('activities')->with('success', 'Activity updated successfully');
    }

    public function destroy(Activity $activity)
    {
        $activity->delete();
        return redirect()->route('activities')->with('success', 'Activity deleted successfully');
    }
}