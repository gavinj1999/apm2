<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:locations,name|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $location = Location::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                ],
                'message' => 'Location created successfully',
            ], 201);
        }

        return redirect()->route('activities')->with('success', 'Location created successfully');
    }

    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:locations,name,' . $location->id . '|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $location->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                ],
                'message' => 'Location updated successfully',
            ]);
        }

        return redirect()->route('activities')->with('success', 'Location updated successfully');
    }

    public function destroy(Location $location)
    {
        $location->delete();

        if (request()->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('activities')->with('success', 'Location deleted successfully');
    }
}