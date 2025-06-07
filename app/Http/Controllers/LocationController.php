<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
public function index(Request $request)
{
    \Log::info('LocationController::index', [
        'user_id' => Auth::id() ?? 'null',
        'params' => $request->all(),
        'cookies' => $request->cookies->all(),
        'headers' => $request->headers->all(),
    ]);
    try {
        $this->authorize('viewAny', Location::class);
    } catch (\Exception $e) {
        \Log::error('Authorization failed', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id(),
        ]);
        throw $e;
    }
    $query = Location::query();
    if ($request->has('type')) {
        $query->where('type', $request->query('type'));
    }
    if ($request->has('service_profile_id')) {
        $query->where('service_profile_id', $request->query('service_profile_id'));
    }
    return response()->json($query->get());
}
    public function store(Request $request)
    {
        $this->authorize('create', Location::class);
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:Home,Depot,Start Rounds,End Rounds',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'service_profile_id' => 'nullable|exists:service_profiles,id',
        ]);

        $location = Location::create($validated);
        return response()->json($location, 201);
    }

    public function update(Request $request, Location $location)
    {
        $this->authorize('update', $location);
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'service_profile_id' => 'required|exists:service_profiles,id',
        ]);

        $location->update($validated);
        return response()->json($location);
    }

    public function destroy(Location $location)
    {
        $this->authorize('delete', $location);
        $location->delete();
        return response()->json(null, 204);
    }
}