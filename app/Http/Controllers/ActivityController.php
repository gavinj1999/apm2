<?php
namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Location;
use App\Models\ActivityDistance;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function index()
    {
        $date = request()->get('date', now()->toDateString());
        $startOfWeek = Carbon::parse($date)->startOfWeek()->startOfDay();
        $endOfWeek = Carbon::parse($date)->endOfWeek()->endOfDay();

        // Fetch all activities to include test data
        $activities = Activity::orderBy('datetime')
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'activity' => $activity->activity,
                    'datetime' => $activity->datetime->toDateTimeString(),
                    'latitude' => $activity->latitude,
                    'longitude' => $activity->longitude,
                    'is_manual' => $activity->is_manual,
                ];
            });

        // Fetch all distances, no date filter
        $distancesQuery = ActivityDistance::query();
        $distancesRaw = $distancesQuery->get()->toArray();
        $distances = $distancesQuery->get()
            ->groupBy(function ($distance) {
                return Carbon::parse($distance->date)->format('Y-m-d');
            })
            ->map(function ($group) {
                return $group->mapWithKeys(function ($distance) {
                    return [$distance->segment => (float) $distance->distance];
                })->toArray();
            })->toArray();

        // Debug: Log raw SQL and all distances
        $allDistances = ActivityDistance::all()->toArray();
        $sqlQuery = $distancesQuery->toSql();
        $sqlBindings = $distancesQuery->getBindings();

        Log::info('ActivityController::index', [
            'date' => $date,
            'startOfWeek' => $startOfWeek->toDateString(),
            'endOfWeek' => $endOfWeek->toDateString(),
            'activity_count' => $activities->count(),
            'activities' => $activities->pluck('id', 'activity')->toArray(),
            'distances_raw' => $distancesRaw,
            'distances_formatted' => $distances,
            'all_distances' => $allDistances,
            'sql_query' => $sqlQuery,
            'sql_bindings' => $sqlBindings,
        ]);

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
            'activityDates' => Activity::pluck('datetime')->map(fn($d) => Carbon::parse($d)->toDateString())->unique()->values(),
            'activityTypes' => ActivityType::all()->map(fn($type) => [
                'name' => $type->name,
                'alias' => $type->name,
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
            'csrf' => csrf_token(),
        ]);
    }

    public function calculateDistances(Request $request)
    {
        $activityId = $request->input('activity_id');
        $date = $request->input('date');
        $segment = $request->input('segment');

        Log::info('Calculate distances request:', [
            'activity_id' => $activityId,
            'date' => $date,
            'segment' => $segment,
        ]);

        if (!$activityId || !$segment || !$date) {
            Log::error('Missing parameters in calculateDistances', [
                'activity_id' => $activityId,
                'date' => $date,
                'segment' => $segment,
            ]);
            return response()->json(['error' => 'Missing required parameters: activity_id, segment, date'], 400);
        }

        if (!in_array($segment, ['home_to_depot', 'depot_to_first_drop', 'last_drop_to_home'])) {
            Log::error('Invalid segment', ['segment' => $segment]);
            return response()->json(['error' => 'Invalid segment'], 400);
        }

        $activity = Activity::find($activityId);
        if (!$activity) {
            Log::error('Activity not found', ['activity_id' => $activityId]);
            return response()->json(['error' => 'Activity not found'], 404);
        }

        if (
            ($segment === 'home_to_depot' && $activity->activity !== 'Left Home') ||
            ($segment === 'depot_to_first_drop' && $activity->activity !== 'Leave Depot') ||
            ($segment === 'last_drop_to_home' && $activity->activity !== 'Last Drop')
        ) {
            Log::error('Activity mismatch', [
                'activity' => $activity->activity,
                'segment' => $segment,
            ]);
            return response()->json(['error' => "Activity {$activity->activity} does not match segment $segment"], 400);
        }

        if (ActivityDistance::where('date', $date)->where('segment', $segment)->exists()) {
            Log::info('Distance already calculated', ['date' => $date, 'segment' => $segment]);
            $existingDistance = ActivityDistance::where('date', $date)->where('segment', $segment)->first();
            return response()->json([
                'message' => 'Distance already calculated',
                'distance' => (float) $existingDistance->distance,
                'date' => $date,
                'segment' => $segment,
            ], 200);
        }

        $mapboxToken = config('services.mapbox.access_token');
        $from = null;
        $to = null;

        if ($segment === 'home_to_depot') {
            $from = Location::where('name', 'Home')->first();
            $to = Location::where('name', 'Depot')->first();
            $toActivity = Activity::where('activity', 'Arrive Depot')->whereDate('datetime', $date)->first();
        } elseif ($segment === 'depot_to_first_drop') {
            $from = Location::where('name', 'Depot')->first();
            $to = Activity::where('activity', 'First Drop')->whereDate('datetime', $date)->first();
            $toActivity = $to;
        } elseif ($segment === 'last_drop_to_home') {
            $from = Activity::where('activity', 'Last Drop')->whereDate('datetime', $date)->first();
            $to = Location::where('name', 'Home')->first();
            $toActivity = Activity::where('activity', 'Arrive Home')->whereDate('datetime', $date)->first();
        }

        if (!$from || !$to) {
            Log::error('Locations or activities not found', [
                'from' => $from ? 'found' : 'missing',
                'to' => $to ? 'found' : 'missing',
                'segment' => $segment,
                'date' => $date,
            ]);
            return response()->json(['error' => 'Required locations or activities not found'], 404);
        }

        $fromLat = $from->latitude;
        $fromLng = $from->longitude;
        $toLat = $to->latitude;
        $toLng = $to->longitude;

        if (!$fromLat || !$fromLng || !$toLat || !$toLng) {
            Log::error('Invalid coordinates', [
                'fromLat' => $fromLat,
                'fromLng' => $fromLng,
                'toLat' => $toLat,
                'toLng' => $toLng,
            ]);
            return response()->json(['error' => 'Invalid coordinates'], 400);
        }

        try {
            $response = Http::withOptions(['verify' => false])->get('https://api.mapbox.com/directions/v5/mapbox/driving/' . 
                "{$fromLng},{$fromLat};{$toLng},{$toLat}", [
                'access_token' => $mapboxToken,
                'geometries' => 'geojson',
            ]);

            if ($response->failed() || !isset($response->json()['routes'][0]['distance'])) {
                Log::error('Mapbox API failed:', ['response' => $response->json()]);
                return response()->json(['error' => 'Failed to calculate distance'], 500);
            }

            // Convert meters to miles (1 meter = 0.000621371 miles)
            $distance = $response->json()['routes'][0]['distance'] * 0.000621371;

            $activityDistance = ActivityDistance::create([
                'date' => $date,
                'segment' => $segment,
                'distance' => $distance,
                'activity_from_id' => $activity->id,
                'activity_to_id' => $toActivity ? $toActivity->id : null,
            ]);

            Log::info('Distance calculated and saved:', [
                'activity_id' => $activityId,
                'date' => $date,
                'segment' => $segment,
                'distance_miles' => $distance,
                'activity_distance_id' => $activityDistance->id,
            ]);

            return response()->json([
                'distance' => (float) $distance,
                'date' => $date,
                'segment' => $segment,
            ]);
        } catch (\Exception $e) {
            Log::error('Distance calculation exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to calculate distance: ' . $e->getMessage()], 500);
        }
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
            'datetime' => $validated['datetime'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'activity' => $activityName,
            'is_manual' => $request->input('is_manual', false),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toDateTimeString(),
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
                    'datetime' => $activity->datetime->toDateTimeString(),
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

        $activity->update(array_filter($validated));

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toDateTimeString(),
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

        if ($activity->id != $validated['id']) {
            return response()->json(['message' => 'Invalid activity ID'], 400);
        }

        $duplicates = Activity::where('id', '!=', $activity->id)
            ->where('activity', $activity->activity)
            ->whereDate('datetime', $activity->datetime->toDateString())
            ->get();

        foreach ($duplicates as $duplicate) {
            $duplicate->delete();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activity->id,
                    'datetime' => $activity->datetime->toDateTimeString(),
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