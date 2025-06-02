<?php

namespace App\Http\Controllers;

use App\Models\ServiceProfile;
use App\Models\Round;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ServiceProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $serviceProfiles = ServiceProfile::where('user_id', $user->id)->with('locations')->get();
        $rounds = Round::where('user_id', $user->id)->pluck('name', 'id');

        $mapboxAccessToken = config('services.mapbox.access_token');
        Log::debug('Mapbox Access Token in ServiceProfileController (index):', [
            'token' => $mapboxAccessToken,
            'config_exists' => config()->has('services.mapbox.access_token'),
            'env_variable' => env('MAPBOX_ACCESS_TOKEN'),
        ]);

        if (!$mapboxAccessToken) {
            Log::error('Mapbox access token is missing in configuration');
            return inertia('ServiceProfile/Index', [
                'serviceProfiles' => $serviceProfiles,
                'rounds' => $rounds,
                'initialLocations' => $serviceProfiles->flatMap->locations,
                'mapboxAccessToken' => null,
                'error' => 'Mapbox access token is missing. Please contact support.',
            ]);
        }

        return inertia('ServiceProfile/Index', [
            'serviceProfiles' => $serviceProfiles->toArray(),
            'rounds' => $rounds->toArray(),
            'initialLocations' => $serviceProfiles->flatMap->locations->toArray(),
            'mapboxAccessToken' => $mapboxAccessToken,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'round_id' => 'required|exists:rounds,id',
            'fuel_cost_per_unit' => 'required|numeric|min:0',
            'distance_unit' => 'required|in:mile,km',
            'distance_home_to_work' => 'required|numeric|min:0',
            'distance_work_to_start' => 'required|numeric|min:0',
            'distance_end_to_home' => 'required|numeric|min:0',
            'loading_time_cost_per_hour' => 'required|numeric|min:0',
            'loading_time_hours' => 'required|numeric|min:0',
            'total_fuel_cost' => 'required|numeric|min:0',
            'total_loading_cost' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
        ]);

        try {
            // Log the data being sent to the database
            $data = array_merge($validated, ['user_id' => $user->id]);
            Log::debug('Creating ServiceProfile with data:', $data);

            $profile = ServiceProfile::create($data);

            Log::info('ServiceProfile created successfully:', ['profile_id' => $profile->id, 'user_id' => $user->id]);

            return redirect()->route('service-profile')->with('success', 'Profile saved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create ServiceProfile: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'validated' => $validated,
            ]);
            return redirect()->route('service-profile')->with('error', 'Failed to save Service Profile: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = Auth::user();
        $serviceProfile = ServiceProfile::where('user_id', $user->id)->with('locations')->findOrFail($id);
        $rounds = Round::where('user_id', $user->id)->pluck('name', 'id');
        $serviceProfiles = ServiceProfile::where('user_id', $user->id)->with('locations')->get();

        $mapboxAccessToken = config('services.mapbox.access_token');
        Log::debug('Mapbox Access Token in ServiceProfileController (edit):', [
            'token' => $mapboxAccessToken,
            'config_exists' => config()->has('services.mapbox.access_token'),
            'env_variable' => env('MAPBOX_ACCESS_TOKEN'),
        ]);

        if (!$mapboxAccessToken) {
            Log::error('Mapbox access token is missing in configuration (edit)');
            return inertia('ServiceProfile/Index', [
                'profile' => $serviceProfile,
                'serviceProfiles' => $serviceProfiles,
                'rounds' => $rounds,
                'initialLocations' => $serviceProfile->locations,
                'mapboxAccessToken' => null,
                'error' => 'Mapbox access token is missing. Please contact support.',
                'isEditing' => true,
            ]);
        }

        return inertia('ServiceProfile/Index', [
            'profile' => $serviceProfile->toArray(),
            'serviceProfiles' => $serviceProfiles->toArray(),
            'rounds' => $rounds->toArray(),
            'initialLocations' => $serviceProfile->locations->toArray(),
            'mapboxAccessToken' => $mapboxAccessToken,
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $serviceProfile = ServiceProfile::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'round_id' => 'required|exists:rounds,id',
            'fuel_cost_per_unit' => 'required|numeric|min:0',
            'distance_unit' => 'required|in:mile,km',
            'distance_home_to_work' => 'required|numeric|min:0',
            'distance_work_to_start' => 'required|numeric|min:0',
            'distance_end_to_home' => 'required|numeric|min:0',
            'loading_time_cost_per_hour' => 'required|numeric|min:0',
            'loading_time_hours' => 'required|numeric|min:0',
            'total_fuel_cost' => 'required|numeric|min:0',
            'total_loading_cost' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
        ]);

        try {
            // Log the data being updated
            Log::debug('Updating ServiceProfile with data:', $validated);

            $serviceProfile->update($validated);

            Log::info('ServiceProfile updated successfully:', ['profile_id' => $serviceProfile->id, 'user_id' => $user->id]);

            return redirect()->route('service-profile')->with('success', 'Service Profile updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update ServiceProfile: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'validated' => $validated,
            ]);
            return redirect()->route('service-profile')->with('error', 'Failed to update Service Profile: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $serviceProfile = ServiceProfile::where('user_id', $user->id)->findOrFail($id);

        try {
            $serviceProfile->locations()->delete();
            $serviceProfile->delete();
            Log::info('ServiceProfile deleted successfully:', ['profile_id' => $id, 'user_id' => $user->id]);
            return redirect()->route('service-profile')->with('success', 'Service Profile deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete ServiceProfile: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'service_profile_id' => $id,
            ]);
            return redirect()->route('service-profile')->with('error', 'Failed to delete Service Profile: ' . $e->getMessage());
        }
    }
}
