<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\ServiceProfile;
use App\Models\Round;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            Log::warning('Unauthenticated attempt to access locations');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $serviceProfile = ServiceProfile::where('user_id', Auth::id())->first();
        if (!$serviceProfile) {
            return response()->json([]);
        }
        return response()->json($serviceProfile->locations);
    }

    public function store(Request $request)
    {
        // Log request headers for debugging
        Log::debug('LocationController::store headers', [
            'Cookies' => $request->header('Cookie'),
            'X-XSRF-TOKEN' => $request->header('X-XSRF-TOKEN'),
        ]);

        if (!Auth::check()) {
            Log::warning('Unauthenticated attempt to store location', ['user_id' => Auth::id()]);
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $userId = Auth::id();
        Log::debug('User ID: ' . $userId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        try {
            // Find an existing ServiceProfile for the user
            $serviceProfile = ServiceProfile::where('user_id', $userId)->first();

            if (!$serviceProfile) {
                // Find or create a default round for the user
                $defaultRound = Round::where('user_id', $userId)->first();
                if (!$defaultRound) {
                    Log::info('No rounds found for user ID: ' . $userId . '. Creating default round.');
                    $defaultRound = Round::create([
                        'user_id' => $userId,
                        'name' => 'Default Round ' . now()->toDateTimeString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                Log::debug('Default Round ID: ' . $defaultRound->id);

                // Create a new ServiceProfile
                $serviceProfile = ServiceProfile::create([
                    'user_id' => $userId,
                    'round_id' => $defaultRound->id,
                    'fuel_cost_per_unit' => 0,
                    'distance_unit' => 'mile',
                    'distance_home_to_work' => 0,
                    'distance_work_to_start' => 0,
                    'distance_end_to_home' => 0,
                    'loading_time_cost_per_hour' => 0,
                    'loading_time_hours' => 0,
                ]);
            }

            Log::debug('ServiceProfile ID: ' . $serviceProfile->id);

            // Create the location associated with the ServiceProfile, including user_id
            $locationData = array_merge($validated, ['user_id' => $userId]);
            $location = $serviceProfile->locations()->create($locationData);
            return response()->json($location, 201);
        } catch (\Exception $e) {
            Log::error('Failed to create ServiceProfile, Round, or Location: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'user_id' => $userId,
                'validated' => $validated,
            ]);
            return response()->json(['error' => 'Failed to save location: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        if (!Auth::check()) {
            Log::warning('Unauthenticated attempt to delete location', ['user_id' => Auth::id()]);
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $serviceProfile = ServiceProfile::where('user_id', Auth::id())->first();
        if (!$serviceProfile) {
            Log::warning('Service profile not found for user', ['user_id' => Auth::id()]);
            return response()->json(['error' => 'Service profile not found'], 404);
        }

        try {
            $location = $serviceProfile->locations()->find($id);
            if ($location) {
                $location->delete();
                Log::debug('Location deleted successfully', ['location_id' => $id, 'user_id' => Auth::id()]);
            } else {
                Log::warning('Location not found for deletion', ['location_id' => $id, 'user_id' => Auth::id()]);
            }
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Failed to delete location: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'location_id' => $id,
                'user_id' => Auth::id(),
            ]);
            return response()->json(['error' => 'Failed to delete location: ' . $e->getMessage()], 500);
        }
    }
}
