<?php

namespace App\Http\Controllers;

use App\Models\Round;
use App\Models\ParcelType;
use App\Models\RoundPricing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoundPricingController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        \Log::info('Authenticated user:', ['id' => $user->id, 'email' => $user->email]);

        // Fetch rounds with their roundPricings and parcelType relationships
        $rounds = Round::where('user_id', auth()->id())
            ->with(['roundPricings' => function ($query) {
                $query->with(['parcelType']);
            }])
            ->get();

        // Log the raw rounds data
        \Log::info('Rounds data (raw):', $rounds->toArray());

        // Transform rounds to ensure roundPricings is always an array and remove duplicate keys
        $rounds = $rounds->map(function ($round) {
            $round->roundPricings = $round->roundPricings ?? [];
            return [
                'id' => $round->id,
                'round_id' => $round->round_id,
                'name' => $round->name,
                'created_at' => $round->created_at,
                'updated_at' => $round->updated_at,
                'user_id' => $round->user_id,
                'roundPricings' => $round->roundPricings->map(function ($pricing) {
                    return [
                        'id' => $pricing->id,
                        'round_id' => $pricing->round_id,
                        'parcel_type_id' => $pricing->parcel_type_id,
                        'price' => $pricing->price,
                        'created_at' => $pricing->created_at,
                        'updated_at' => $pricing->updated_at,
                        'parcel_type' => $pricing->parcelType ? [
                            'id' => $pricing->parcelType->id,
                            'name' => $pricing->parcelType->name,
                            'created_at' => $pricing->parcelType->created_at,
                            'updated_at' => $pricing->parcelType->updated_at,
                            'round_id' => $pricing->parcelType->round_id,
                        ] : null,
                    ];
                })->toArray(),
            ];
        });

        // Fetch parcel types available for each round based on round_pricings
        $parcelTypes = RoundPricing::whereIn('round_id', $rounds->pluck('id'))
            ->with('parcelType')
            ->get()
            ->groupBy('round_id')
            ->mapWithKeys(function ($group, $roundId) {
                return [$roundId => $group->map(function ($roundPricing) {
                    return [
                        'id' => $roundPricing->parcel_type_id,
                        'name' => $roundPricing->parcelType->name ?? 'Unknown',
                    ];
                })->unique('id')->values()];
            });

        \Log::info('ParcelTypes data:', $parcelTypes->toArray());

        return Inertia::render('Prices', [
            'rounds' => $rounds,
            'parcelTypes' => $parcelTypes,
            'flash' => session('flash', []),
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('Store method called with data:', $request->all());

        $validated = $request->validate([
            'round_id' => 'required|exists:rounds,id',
            'parcel_type_id' => 'required|exists:parcel_types,id',
            'price' => 'required|numeric|min:0',
        ]);

        \Log::info('Validated data:', $validated);

        $round = Round::where('id', $validated['round_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            \Log::error('Round does not belong to user:', ['round_id' => $validated['round_id'], 'user_id' => auth()->id()]);
            return redirect()->route('prices.index')->with('error', 'Selected round does not belong to you.');
        }

        // Check if the parcel type is already associated with the round
        $existingRoundPricing = RoundPricing::where('round_id', $validated['round_id'])
            ->where('parcel_type_id', $validated['parcel_type_id'])
            ->first();

        if ($existingRoundPricing) {
            $existingRoundPricing->update([
                'price' => $validated['price'],
            ]);
            \Log::info('Round pricing updated:', $existingRoundPricing->toArray());
            return redirect()->route('prices.index')->with('success', 'Price updated successfully!');
        }

        $roundPricing = RoundPricing::create([
            'round_id' => $validated['round_id'],
            'parcel_type_id' => $validated['parcel_type_id'],
            'price' => $validated['price'],
        ]);

        \Log::info('Round pricing created:', $roundPricing->toArray());

        return redirect()->route('prices.index')->with('success', 'Price added successfully!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $roundPricing = RoundPricing::find($id);

        if (!$roundPricing) {
            return redirect()->route('prices.index')->with('error', 'Price not found.');
        }

        $round = Round::where('id', $roundPricing->round_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('prices.index')->with('error', 'Selected round does not belong to you.');
        }

        $roundPricing->update([
            'price' => $validated['price'],
        ]);

        return redirect()->route('prices.index')->with('success', 'Price updated successfully!');
    }

    public function destroy($id)
    {
        $roundPricing = RoundPricing::find($id);

        if (!$roundPricing) {
            return redirect()->route('prices.index')->with('error', 'Price not found.');
        }

        $round = Round::where('id', $roundPricing->round_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('prices.index')->with('error', 'Selected round does not belong to you.');
        }

        $roundPricing->delete();

        return redirect()->route('prices.index')->with('success', 'Price deleted successfully!');
    }
}
