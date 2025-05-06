<?php

namespace App\Http\Controllers;

use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WorkLocationController extends Controller
{
    public function index()
    {
        $workLocations = WorkLocation::all();
        return response()->json($workLocations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:2048', // Max 2MB
        ]);

        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('work_locations', 'public');
                $validated['image'] = $path;
            }

            $workLocation = WorkLocation::create($validated);
            return response()->json($workLocation, 201);
        } catch (\Exception $e) {
            Log::error('Failed to create WorkLocation: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'validated' => $validated,
            ]);
            return response()->json(['error' => 'Failed to create WorkLocation: ' . $e->getMessage()], 500);
        }
    }
}
