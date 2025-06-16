<?php
namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Location;
use App\Models\Distance;
use App\Models\ActivityDistance;
use App\Models\ParcelType;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ActivityController extends Controller
{
    public function index()
    {
        $date = request()->get('date', now()->toDateString());
        $startOfWeek = Carbon::parse($date)->startOfWeek()->toDateString();
        $endOfWeek = Carbon::parse($date)->endOfWeek()->toDateString();
        $perPage = request()->get('per_page', 10);

        $activities = Activity::whereBetween('datetime', [$startOfWeek, $endOfWeek])
            ->orderBy('datetime')
            ->paginate($perPage)
            ->through(function ($activity) {
                return [
                    'id' => $activity->id,
                    'activity' => $activity->activity,
                    'datetime' => $activity->datetime->toDateTimeString(),
                    'latitude' => $activity->latitude,
                    'longitude' => $activity->longitude,
                    'is_manual' => $activity->is_manual,
                ];
            });

        // Fetch distances for the week
        $distances = Distance::whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get()
            ->groupBy('date')
            ->map(function ($group) {
                return $group->mapWithKeys(function ($distance) {
                    return [$distance->segment => $distance->distance];
                })->toArray();
            })->toArray();

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
            'activityDates' => Activity::pluck('datetime')->map(fn($date) => Carbon::parse($date)->toDateString())->unique()->values(),
            'activityTypes' => ActivityType::all()->map(fn($type) => [
                'name' => $type->name,
                'alias' => $type->alias,
                'color' => $type->color,
            ]),
            'locations' => Location::all()->map(fn($location) => [
                'id' => $location->id,
                'name' => $location->name,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
            ]),
            'distances' => $distances,
            'mapboxToken' => config('services.mapbox.access_token'),
        ]);
    }

    public function calculateDistances(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $startOfWeek = Carbon::parse($date)->startOfWeek()->toDateString();
        $endOfWeek = Carbon::parse($date)->endOfWeek()->toDateString();

        $activities = Activity::whereBetween('datetime', [$startOfWeek, $endOfWeek])
            ->orderBy('datetime')
            ->get()
            ->groupBy(fn($activity) => Carbon::parse($activity->datetime)->toDateString());

        $mapboxToken = config('services.mapbox.access_token');
        $distances = [];

        foreach ($activities as $date => $dailyActivities) {
            $segments = [
                'home_to_depot' => ['Left Home', 'Arrive Depot'],
                'depot_to_first_drop' => ['Leave Depot', 'First Drop'],
                'last_drop_to_home' => ['Last Drop', 'Arrive Home'],
            ];

            foreach ($segments as $segment => [$fromActivity, $toActivity]) {
                // Check if distance already exists
                if (ActivityDistance::where('date', $date)->where('segment', $segment)->exists()) {
                    continue;
                }

                $from = $dailyActivities->firstWhere('activity', $fromActivity);
                $to = $dailyActivities->firstWhere('activity', $toActivity);

                if (!$from || !$to || !$from->latitude || !$from->longitude || !$to->latitude || !$to->longitude) {
                    continue; // Skip if activities or coordinates are missing
                }

                // Call Mapbox Directions API
                $response = Http::get('https://api.mapbox.com/directions/v5/mapbox/driving/' . 
                    "{$from->longitude},{$from->latitude};{$to->longitude},{$to->latitude}", [
                    'access_token' => $mapboxToken,
                    'geometries' => 'geojson',
                ]);

                if ($response->failed() || !isset($response->json()['routes'][0]['distance'])) {
                    continue; // Skip on API error
                }

                $distance = $response->json()['routes'][0]['distance'] / 1000; // Convert meters to kilometers

                // Store distance
                ActivityDistance::create([
                    'date' => $date,
                    'segment' => $segment,
                    'distance' => $distance,
                    'unit' => 'km',
                    'activity_from_id' => $from->id,
                    'activity_to_id' => $to->id,
                ]);

                $distances[] = [
                    'date' => $date,
                    'segment' => $segment,
                    'distance' => $distance,
                    'unit' => 'km',
                ];
            }
        }

        return response()->json(['distances' => $distances]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|exists:activity_types,name',
            'is_manual' => 'sometimes|boolean',
        ]);

        // Adjust datetime to account for BST (subtract 1 hour if in BST)
        $datetime = Carbon::parse($validated['datetime']);
        if ($datetime->isDST() && $datetime->timezoneName === 'Europe/London') {
            $datetime->subHour();
        }

        // Override coordinates for Home/Depot activities
        $latitude = $validated['latitude'];
        $longitude = $validated['longitude'];
        $activityName = $validated['activity'];

        if (in_array($activityName, ['Left Home', 'Arrive Home'])) {
            $home = Location::where('name', 'Home')->first();
            if ($home) {
                $latitude = $home->latitude;
                $longitude = $home->longitude;
            }
        } elseif (in_array($activityName, ['Arrive Depot', 'Start Loading', 'Leave Depot'])) {
            $depot = Location::where('name', 'Depot')->first();
            if ($depot) {
                $latitude = $depot->latitude;
                $longitude = $depot->longitude;
            }
        }

        $activity = Activity::create([
            'datetime' => $datetime,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'activity' => $activityName,
            'is_manual' => $request->input('is_manual', false),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toISOString(),
                    'latitude' => (float) $activity->latitude,
                    'longitude' => (float) $activity->longitude,
                    'activity' => $activity->activity,
                    'is_manual' => $activity->is_manual,
                ],
                'message' => 'Activity created successfully',
            ], 201);
        }

        return redirect()->route('activities')->with('success', 'Activity created successfully');
    }

    public function update(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|exists:activity_types,name',
            'is_manual' => 'sometimes|boolean',
        ]);

        // Adjust datetime to account for BST (subtract 1 hour if in BST)
        $datetime = Carbon::parse($validated['datetime']);
        if ($datetime->isDST() && $datetime->timezoneName === 'Europe/London') {
            $datetime->subHour();
        }
        $validated['datetime'] = $datetime;

        // Override coordinates for Home/Depot activities
        $latitude = $validated['latitude'];
        $longitude = $validated['longitude'];
        $activityName = $validated['activity'];

        if (in_array($activityName, ['Left Home', 'Arrive Home'])) {
            $home = Location::where('name', 'Home')->first();
            if ($home) {
                $latitude = $home->latitude;
                $longitude = $home->longitude;
            }
        } elseif (in_array($activityName, ['Arrive Depot', 'Start Loading', 'Leave Depot'])) {
            $depot = Location::where('name', 'Depot')->first();
            if ($depot) {
                $latitude = $depot->latitude;
                $longitude = $depot->longitude;
            }
        }

        $validated['latitude'] = $latitude;
        $validated['longitude'] = $longitude;

        $activity->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toISOString(),
                    'latitude' => (float) $activity->latitude,
                    'longitude' => (float) $activity->longitude,
                    'activity' => $activity->activity,
                    'is_manual' => $activity->is_manual,
                ],
                'message' => 'Activity updated successfully',
            ]);
        }

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

        if (isset($validated['datetime'])) {
            $datetime = Carbon::parse($validated['datetime']);
            if ($datetime->isDST() && $datetime->timezoneName === 'Europe/London') {
                $datetime->subHour();
            }
            $validated['datetime'] = $datetime;
        }

        $activity->update(array_filter($validated));

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toISOString(),
                    'latitude' => (float) $activity->latitude,
                    'longitude' => (float) $activity->longitude,
                    'activity' => $activity->activity,
                    'is_manual' => $activity->is_manual,
                ],
                'message' => 'Activity updated successfully',
            ]);
        }

        return redirect()->route('activities')->with('success', 'Activity updated successfully');
    }

    public function destroy(Activity $activity)
    {
        $activity->delete();

        if (request()->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('activities')->with('success', 'Activity deleted successfully');
    }

    public function markAsCorrect(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'id' => 'required|exists:activities,id',
        ]);

        // Ensure the activity passed matches the ID
        if ($activity->id != $validated['id']) {
            return response()->json(['message' => 'Invalid activity ID'], 400);
        }

        // Find duplicates: same activity type, same date
        $duplicates = Activity::where('id', '!=', $activity->id)
            ->where('activity', $activity->activity)
            ->whereDate('datetime', $activity->datetime->toDateString())
            ->get();

        // Delete duplicates
        foreach ($duplicates as $duplicate) {
            $duplicate->delete();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toISOString(),
                    'latitude' => (float) $activity->latitude,
                    'longitude' => (float) $activity->longitude,
                    'activity' => $activity->activity,
                    'is_manual' => $activity->is_manual,
                ],
                'message' => 'Duplicates removed, activity marked as correct',
            ]);
        }

        return redirect()->route('activities')->with('success', 'Duplicates removed, activity marked as correct');
    }
}