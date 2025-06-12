<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    public function store(Request $request)
    {
        Log::info('ActivityController::store called', ['request' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|in:Left Home,Arrive Depot,Start Loading,Leave Depot,First Drop,Last Drop,Arrive Home',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['latitude', 'longitude', 'activity']);
        $data['datetime'] = \Carbon\Carbon::parse($request->datetime)->setTimezone('UTC');
        $data['latitude'] = (float) $request->latitude;
        $data['longitude'] = (float) $request->longitude;

        $activity = Activity::create($data);

        Log::info('Activity created', ['activity' => $activity]);

        return response()->json(['message' => 'Activity recorded successfully', 'data' => $activity], 201);
    }

    public function index(Request $request)
    {
        Log::info('ActivityController::index called', ['request' => $request->all()]);

        $query = Activity::latest();

        if ($request->has('date')) {
            $date = \Carbon\Carbon::parse($request->date)->startOfDay();
            $query->whereDate('datetime', $date);
        }

        $activities = $query->get()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'datetime' => $activity->datetime->toISOString(),
                'latitude' => (float) $activity->latitude,
                'longitude' => (float) $activity->longitude,
                'activity' => $activity->activity,
            ];
        });

        return response()->json(['data' => $activities]);
    }

    public function update(Request $request, Activity $activity)
    {
        Log::info('ActivityController::update called', ['request' => $request->all(), 'activity_id' => $activity->id]);

        $validator = Validator::make($request->all(), [
            'datetime' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity' => 'required|string|in:Left Home,Arrive Depot,Start Loading,Leave Depot,First Drop,Last Drop,Arrive Home',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['latitude', 'longitude', 'activity']);
        $data['datetime'] = \Carbon\Carbon::parse($request->datetime)->setTimezone('UTC');

        $activity->update($data);

        Log::info('Activity updated', ['activity' => $activity]);

        return response()->json(['message' => 'Activity updated successfully', 'data' => [
            'id' => $activity->id,
            'datetime' => $activity->datetime->toISOString(),
            'latitude' => (float) $activity->latitude,
            'longitude' => (float) $activity->longitude,
            'activity' => $activity->activity,
        ]]);
    }

    public function destroy(Activity $activity)
    {
        Log::info('ActivityController::destroy called', ['activity_id' => $activity->id]);

        $activity->delete();

        Log::info('Activity deleted', ['activity_id' => $activity->id]);

        return response()->json(['message' => 'Activity deleted successfully']);
    }
}