<?php

namespace App\Http\Controllers;

use App\Models\ParcelType;
use App\Models\Round;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParcelTypeController extends Controller
{
    public function index()
    {
        $parcelTypes = ParcelType::with('round')->whereHas('round', function ($query) {
            $query->where('user_id', auth()->id());
        })->get();

        $rounds = Round::where('user_id', auth()->id())->get();

        return Inertia::render('ParcelTypes/Index', [
            'parcelTypes' => $parcelTypes,
            'rounds' => $rounds,
            'flash' => session('flash', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'round_id' => 'required|exists:rounds,id',
        ]);

        $round = Round::where('id', $validated['round_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('parcel-types.index')->with('error', 'Selected round does not belong to you.');
        }

        ParcelType::create($validated);

        return redirect()->route('parcel-types.index')->with('success', 'Parcel type created successfully!');
    }

    public function update(Request $request, ParcelType $parcelType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'round_id' => 'required|exists:rounds,id',
        ]);

        $round = Round::where('id', $validated['round_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('parcel-types.index')->with('error', 'Selected round does not belong to you.');
        }

        $parcelType->update($validated);

        return redirect()->route('parcel-types.index')->with('success', 'Parcel type updated successfully!');
    }

    public function destroy(ParcelType $parcelType)
    {
        $round = Round::where('id', $parcelType->round_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('parcel-types.index')->with('error', 'Selected round does not belong to you.');
        }

        $parcelType->delete();

        return redirect()->route('parcel-types.index')->with('success', 'Parcel type deleted successfully!');
    }
}
