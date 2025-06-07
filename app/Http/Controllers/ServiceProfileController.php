<?php

namespace App\Http\Controllers;

use App\Models\ServiceProfile;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ServiceProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            Log::error('No authenticated user found in ServiceProfileController::index');
            return redirect()->route('login')->with('error', 'Please log in to access this page');
        }

        $serviceProfiles = ServiceProfile::where('user_id', $user->id)->get();
        $currentProfile = $serviceProfiles->first(); // Default to first profile or null
        $mapboxAccessToken = config('services.mapbox.access_token');

        if (!$mapboxAccessToken) {
            Log::error('Mapbox access token is missing in configuration');
            return inertia('ServiceProfile/Index', [
                'serviceProfiles' => $serviceProfiles,
                'profile' => $currentProfile,
                'mapboxAccessToken' => null,
                'error' => 'Mapbox access token is missing. Please contact support.',
            ]);
        }

        return inertia('ServiceProfile/Index', [
            'serviceProfiles' => $serviceProfiles->toArray(),
            'profile' => $currentProfile ? $currentProfile->toArray() : null,
            'mapboxAccessToken' => $mapboxAccessToken,
            'isEditing' => false,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            Log::error('No authenticated user found in ServiceProfileController::store');
            return redirect()->route('login')->with('error', 'Please log in to create a profile');
        }

        $validated = $request->validate([
            'fuel_cost_per_unit' => 'required|numeric|min:0',
            'distance_unit' => 'required|in:mile,km',
            'distance_home_to_depot' => 'required|numeric|min:0',
            'distance_depot_to_start' => 'required|numeric|min:0',
            'distance_end_to_home' => 'required|numeric|min:0',
            'loading_time_minutes' => 'required|integer|min:0',
            'loading_time_cost_per_hour' => 'required|numeric|min:0',
            'total_fuel_cost' => 'required|numeric|min:0',
            'total_loading_cost' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
        ]);

        try {
            $data = array_merge($validated, ['user_id' => $user->id]);
            Log::debug('Creating ServiceProfile with data:', $data);
            $profile = ServiceProfile::create($data);
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
        if (!$user) {
            Log::error('No authenticated user found in ServiceProfileController::edit');
            return redirect()->route('login')->with('error', 'Please log in to access this page');
        }

        $serviceProfile = ServiceProfile::where('user_id', $user->id)->findOrFail($id);
        $serviceProfiles = ServiceProfile::where('user_id', $user->id)->get();
        $mapboxAccessToken = config('services.mapbox.access_token');

        if (!$mapboxAccessToken) {
            Log::error('Mapbox access token is missing in configuration (edit)');
            return inertia('ServiceProfile/Index', [
                'serviceProfiles' => $serviceProfiles,
                'profile' => $serviceProfile,
                'mapboxAccessToken' => null,
                'error' => 'Mapbox access token is missing. Please contact support.',
                'isEditing' => true,
            ]);
        }

        return inertia('ServiceProfile/Index', [
            'serviceProfiles' => $serviceProfiles->toArray(),
            'profile' => $serviceProfile->toArray(),
            'mapboxAccessToken' => $mapboxAccessToken,
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            Log::error('No authenticated user found in ServiceProfileController::update');
            return redirect()->route('login')->with('error', 'Please log in to update a profile');
        }

        $serviceProfile = ServiceProfile::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'fuel_cost_per_unit' => 'required|numeric|min:0',
            'distance_unit' => 'required|in:mile,km',
            'distance_home_to_depot' => 'required|numeric|min:0',
            'distance_depot_to_start' => 'required|numeric|min:0',
            'distance_end_to_home' => 'required|numeric|min:0',
            'loading_time_minutes' => 'required|integer|min:0',
            'loading_time_cost_per_hour' => 'required|numeric|min:0',
            'total_fuel_cost' => 'required|numeric|min:0',
            'total_loading_cost' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
        ]);

        try {
            Log::debug('Updating ServiceProfile with data:', $validated);
            $serviceProfile->update($validated);
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
        if (!$user) {
            Log::error('No authenticated user found in ServiceProfileController::destroy');
            return redirect()->route('login')->with('error', 'Please log in to delete a profile');
        }

        $serviceProfile = ServiceProfile::where('user_id', $user->id)->findOrFail($id);

        try {
            $serviceProfile->locations()->delete();
            $serviceProfile->delete();
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