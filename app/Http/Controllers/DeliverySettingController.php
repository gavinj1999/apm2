<?php

namespace App\Http\Controllers;

use App\Models\DeliverySetting;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliverySettingController extends Controller
{
    public function index()
    {
        $settings = DeliverySetting::all()->pluck('value', 'key')->toArray();

        Log::info('DeliverySettingsController::index', [
            'settings' => $settings,
        ]);

        return Inertia::render('DeliverySettings/Index', [
            'settings' => $settings,
            'csrf' => csrf_token(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'home_to_depot_distance' => 'required|numeric|min:0',
            'first_drop_distance' => 'required|numeric|min:0',
            'last_drop_distance' => 'required|numeric|min:0',
        ]);

        DeliverySetting::updateOrCreate(
            ['key' => 'home_to_depot_distance'],
            ['value' => $validated['home_to_depot_distance']]
        );
        DeliverySetting::updateOrCreate(
            ['key' => 'first_drop_distance'],
            ['value' => $validated['first_drop_distance']]
        );
        DeliverySetting::updateOrCreate(
            ['key' => 'last_drop_distance'],
            ['value' => $validated['last_drop_distance']]
        );

        Log::info('Delivery settings updated:', $validated);

        return redirect()->route('delivery-settings')->with('success', 'Delivery settings updated successfully');
    }
}