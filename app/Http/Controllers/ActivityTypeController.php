<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityTypeController extends Controller
{
    public function index()
    {
        $activityTypes = ActivityType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
                'alias' => $type->alias,
                'color' => $type->color,
                'created_at' => $type->created_at->toISOString(),
                'updated_at' => $type->updated_at->toISOString(),
            ];
        });

        return Inertia::render('ActivityTypes/Index', [
            'activityTypes' => $activityTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:activity_types,name|max:255',
            'alias' => 'required|string|max:50',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $activityType = ActivityType::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activityType->id,
                    'name' => $activityType->name,
                    'alias' => $activityType->alias,
                    'color' => $activityType->color,
                ],
                'message' => 'Activity type created successfully',
            ], 201);
        }

        return redirect()->route('activity-types.index')->with('success', 'Activity type created successfully');
    }

    public function update(Request $request, ActivityType $activityType)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:activity_types,name,' . $activityType->id . '|max:255',
            'alias' => 'required|string|max:50',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $activityType->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $activityType->id,
                    'name' => $activityType->name,
                    'alias' => $activityType->alias,
                    'color' => $activityType->color,
                ],
                'message' => 'Activity type updated successfully',
            ]);
        }

        return redirect()->route('activity-types.index')->with('success', 'Activity type updated successfully');
    }

    public function destroy(ActivityType $activityType)
    {
        // Prevent deletion if activities use this type
        if ($activityType->activities()->exists()) {
            return redirect()->route('activity-types.index')->with('error', 'Cannot delete activity type with associated activities');
        }

        $activityType->delete();

        if (request()->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('activity-types.index')->with('success', 'Activity type deleted successfully');
    }
}