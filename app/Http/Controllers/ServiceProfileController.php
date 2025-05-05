<?php

namespace App\Http\Controllers;

use App\Models\ServiceProfile;
use App\Models\Round;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ServiceProfileController extends Controller
{
    public function index()
    {
        $profile = ServiceProfile::where('user_id', auth()->id())->first();
        $rounds = Round::where('user_id', auth()->id())->get()->mapWithKeys(function ($round) {
            return [$round->id => $round->name ?? $round->round_id];
        })->toArray();
        $locations = $profile ? $profile->locations : [];

        return Inertia::render('ServiceProfile/Index', [
            'profile' => $profile,
            'rounds' => $rounds,
            'flash' => session('flash', []),
            'initialLocations' => $locations,
            'mapboxAccessToken' => env('MAPBOX_ACCESS_TOKEN'), // Pass Mapbox token
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'round_id' => 'nullable|exists:rounds,id',
            'fuel_cost_per_unit' => 'required|numeric|min:0',
            'distance_unit' => 'required|in:mile,km',
            'distance_home_to_work' => 'required|numeric|min:0',
            'distance_work_to_start' => 'required|numeric|min:0',
            'distance_end_to_home' => 'required|numeric|min:0',
            'loading_time_cost_per_hour' => 'required|numeric|min:0',
            'loading_time_hours' => 'required|numeric|min:0',
        ]);

        $profile = ServiceProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            $validated
        );

        return redirect()->route('service-profile')->with('success', 'Profile saved successfully');
    }
}
