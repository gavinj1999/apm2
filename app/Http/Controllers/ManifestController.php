<?php
namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\ManifestSummary;
use App\Models\ParcelType;
use App\Models\Round;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManifestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'manifest_number' => 'required|string|unique:manifests|max:255',
            'delivery_date' => 'required|date',
            'status' => 'required|in:pending,in-progress,completed',
            'round_id' => 'required|exists:rounds,id',
            'quantities' => 'required|array',
            'quantities.*.parcel_type_id' => 'required|exists:parcel_types,id',
            'quantities.*.manifested' => 'required|integer|min:0',
            'quantities.*.re_manifested' => 'required|integer|min:0',
            'quantities.*.carried_forward' => 'required|integer|min:0',
        ]);

        $manifest = Manifest::create([
            'manifest_number' => $validated['manifest_number'],
            'delivery_date' => $validated['delivery_date'],
            'status' => $validated['status'],
            'user_id' => auth()->id(),
            'round_id' => $validated['round_id']
        ]);

        foreach ($validated['quantities'] as $quantity) {
            ManifestSummary::create([
                'manifest_id' => $manifest->id,
                'parcel_type_id' => $quantity['parcel_type_id'],
                'manifested' => $quantity['manifested'],
                're_manifested' => $quantity['re_manifested'],
                'carried_forward' => $quantity['carried_forward']
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Manifest created successfully!');
    }

    public function getById($id)
    {
        $manifest = Manifest::with(['summaries', 'round'])
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$manifest) {
            return response()->json(['error' => 'Manifest not found'], 404);
        }

        $quantities = $manifest->summaries->map(function ($summary) {
            return [
                'parcel_type_id' => $summary->parcel_type_id,
                'manifested' => $summary->manifested,
                're_manifested' => $summary->re_manifested,
                'carried_forward' => $summary->carried_forward,
            ];
        });

        return response()->json([
            'delivery_date' => $manifest->delivery_date,
            'status' => $manifest->status,
            'round_id' => $manifest->round_id,
            'quantities' => $quantities,
        ]);
    }

    public function updateById(Request $request, $id)
    {
        $validated = $request->validate([
            'delivery_date' => 'required|date',
            'status' => 'required|in:pending,in-progress,completed',
            'round_id' => 'required|exists:rounds,id',
            'quantities' => 'required|array',
            'quantities.*.parcel_type_id' => 'required|exists:parcel_types,id',
            'quantities.*.manifested' => 'required|integer|min:0',
            'quantities.*.re_manifested' => 'required|integer|min:0',
            'quantities.*.carried_forward' => 'required|integer|min:0',
        ]);

        $manifest = Manifest::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$manifest) {
            return redirect()->route('dashboard')->with('error', 'Manifest not found.');
        }

        $manifest->update([
            'delivery_date' => $validated['delivery_date'],
            'status' => $validated['status'],
            'round_id' => $validated['round_id'],
        ]);

        // Delete existing summaries and recreate them
        $manifest->summaries()->delete();
        foreach ($validated['quantities'] as $quantity) {
            ManifestSummary::create([
                'manifest_id' => $manifest->id,
                'parcel_type_id' => $quantity['parcel_type_id'],
                'manifested' => $quantity['manifested'],
                're_manifested' => $quantity['re_manifested'],
                'carried_forward' => $quantity['carried_forward'],
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Manifest updated successfully!');
    }

    public function deleteById($id)
    {
        $manifest = Manifest::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$manifest) {
            return redirect()->route('dashboard')->with('error', 'Manifest not found.');
        }

        $manifest->summaries()->delete();
        $manifest->delete();

        return redirect()->route('dashboard')->with('success', 'Manifest deleted successfully!');
    }
}
