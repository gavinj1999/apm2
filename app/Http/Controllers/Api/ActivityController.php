<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActivityController extends Controller
{
public function store(Request $request)
{
    //dd($request->all());
    $validator = Validator::make($request->all(), [
        'datetime' => 'required|date', // Accepts any valid date format
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'activity' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $activity = Activity::create($request->only(['datetime', 'latitude', 'longitude', 'activity']));

    return response()->json(['message' => 'Activity recorded successfully', 'data' => $activity], 201);
}

    public function index()
    {
        $activities = Activity::latest()->get();
     

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
        ]);
    }
}
