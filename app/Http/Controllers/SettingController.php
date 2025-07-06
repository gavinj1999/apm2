<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        Log::info('SettingsController::index', [
            'settings' => $settings,
        ]);

        return Inertia::render('AppSettings/Index', [
            'settings' => $settings,
            'csrf' => csrf_token(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'first_drop_distance' => 'required|numeric|min:0',
            'last_drop_distance' => 'required|numeric|min:0',
        ]);

        Setting::updateOrCreate(
            ['key' => 'first_drop_distance'],
            ['value' => $validated['first_drop_distance']]
        );
        Setting::updateOrCreate(
            ['key' => 'last_drop_distance'],
            ['value' => $validated['last_drop_distance']]
        );

        Log::info('Settings updated:', $validated);

        return redirect()->route('settings')->with('success', 'Settings updated successfully');
    }
}