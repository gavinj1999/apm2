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

        $serviceProfileId = request()->query('service_profile_id');
        if (!$serviceProfileId) {
            return response()->json([]);
        }

        $serviceProfile = ServiceProfile::where('user_id', Auth::id())->find($serviceProfileId);
        if (!$serviceProfile) {
            return response()->json([]);
        }

        return response()->json($serviceProfile->locations);
    }

    public function store(Request $request)
    {
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
            'service_profile_id' => 'required|exists:service_profiles,id',
        ]);

        try {
            $serviceProfile = ServiceProfile::where('user_id', $userId)
                ->find($validated['service_profile_id']);

            if (!$serviceProfile) {
                Log::warning('Service profile not found for user', [
                    'user_id' => $userId,
                    'service_profile_id' => $validated['service_profile_id'],
                ]);
                return response()->json(['error' => 'Service profile not found'], 404);
            }

            $locationData = array_merge($validated, ['user_id' => $userId]);
            $location = $serviceProfile->locations()->create($locationData);
            return response()->json($location, 201);
        } catch (\Exception $e) {
            Log::error('Failed to create Location: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'user_id' => $userId,
                'validated' => $validated,
            ]);
            return response()->json(['error' => 'Failed to save location: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            Log::warning('Unauthenticated attempt to update location', ['user_id' => Auth::id()]);
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $serviceProfileId = request()->query('service_profile_id');
        $serviceProfile = ServiceProfile::where('user_id', Auth::id())->find($serviceProfileId);
        if (!$serviceProfile) {
            Log::warning('Service profile not found for user', ['user_id' => Auth::id()]);
            return response()->json(['error' => 'Service profile not found'], 404);
        }

        $location = $serviceProfile->locations()->find($id);
        if (!$location) {
            Log::warning('Location not found for update', ['location_id' => $id, 'user_id' => Auth::id()]);
            return response()->json(['error' => 'Location not found'], 404);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        try {
            $location->update($validated);
            Log::debug('Location updated successfully', ['location_id' => $id, 'user_id' => Auth::id(), 'new_position' => $validated]);
            return response()->json($location, 200);
        } catch (\Exception $e) {
            Log::error('Failed to update location: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'location_id' => $id,
                'user_id' => Auth::id(),
                'validated' => $validated,
            ]);
            return response()->json(['error' => 'Failed to update location: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        if (!Auth::check()) {
            Log::warning('Unauthenticated attempt to delete location', ['user_id' => Auth::id()]);
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $serviceProfileId = request()->query('service_profile_id');
        $serviceProfile = ServiceProfile::where('user_id', Auth::id())->find($serviceProfileId);
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
