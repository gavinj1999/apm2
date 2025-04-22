<?php

namespace App\Http\Controllers;

use App\Models\Round;
use App\Models\ParcelType;
use App\Models\RoundPricing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index()
    {
        // Fetch rounds for the authenticated user with their pricings and parcel types
        $rounds = Round::where('user_id', auth()->id())
            ->with(['pricings.parcelType'])
            ->get();

        // Fetch all parcel types
        $parcelTypes = ParcelType::all();

        return Inertia::render('Prices', [
            'rounds' => $rounds,
            'parcelTypes' => $parcelTypes,
            'flash' => session('flash', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'round_id' => 'required|exists:rounds,id',
            'parcel_type_id' => 'required|exists:parcel_types,id',
            'price' => 'required|numeric|min:0',
        ]);

        // Verify the round belongs to the authenticated user
        $round = Round::where('id', $validated['round_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('prices.index')->with('error', 'Selected round does not belong to you.');
        }

        // Check if a pricing already exists for this round and parcel type
        $existingPricing = RoundPricing::where('round_id', $validated['round_id'])
            ->where('parcel_type_id', $validated['parcel_type_id'])
            ->first();

        if ($existingPricing) {
            return redirect()->route('prices.index')->with('error', 'A price for this round and parcel type already exists.');
        }

        RoundPricing::create([
            'round_id' => $validated['round_id'],
            'parcel_type_id' => $validated['parcel_type_id'],
            'price' => $validated['price'],
        ]);

        return redirect()->route('prices.index')->with('success', 'Price added successfully!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $pricing = RoundPricing::find($id);

        if (!$pricing) {
            return redirect()->route('prices.index')->with('error', 'Price not found.');
        }

        // Verify the round belongs to the authenticated user
        $round = Round::where('id', $pricing->round_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('prices.index')->with('error', 'Selected round does not belong to you.');
        }

        $pricing->update([
            'price' => $validated['price'],
        ]);

        return redirect()->route('prices.index')->with('success', 'Price updated successfully!');
    }

    public function destroy($id)
    {
        $pricing = RoundPricing::find($id);

        if (!$pricing) {
            return redirect()->route('prices.index')->with('error', 'Price not found.');
        }

        // Verify the round belongs to the authenticated user
        $round = Round::where('id', $pricing->round_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('prices.index')->with('error', 'Selected round does not belong to you.');
        }

        $pricing->delete();

        return redirect()->route('prices.index')->with('success', 'Price deleted successfully!');
    }
}
