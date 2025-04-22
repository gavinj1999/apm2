<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Round;
use App\Models\ParcelType;
use App\Models\ManifestSummary; // Updated to use ManifestSummary
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManifestController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $rounds = Round::where('user_id', $user->id)->get();
        $roundIds = $rounds->pluck('id')->toArray(); // Get valid round IDs
        $parcelTypes = ParcelType::all();

        // Fetch manifests with their quantities, only for valid round IDs
        $manifests = Manifest::whereIn('round_id', $roundIds)
            ->with(['round', 'quantities'])
            ->get();

        // Group manifests by period, then by date, and then by round
        $groupedManifests = $manifests->groupBy(function ($manifest) {
            return date('F Y', strtotime($manifest->delivery_date)); // e.g., "April 2025"
        })->map(function ($group, $periodName) use ($rounds, $parcelTypes) {
            // Group by date
            $byDate = $group->groupBy('delivery_date')->map(function ($dateGroup, $date) use ($rounds, $parcelTypes) {
                // Group by round within each date
                $byRound = $dateGroup->groupBy('round_id')->map(function ($roundGroup, $roundId) use ($rounds, $parcelTypes, $date) {
                    // Aggregate quantities for all manifests in this round on this date
                    $quantities = $parcelTypes->map(function ($type) use ($roundGroup) {
                        $totalManifested = $roundGroup->sum(function ($manifest) use ($type) {
                            return $manifest->quantities->where('parcel_type_id', $type->id)->sum('manifested');
                        });
                        $totalReManifested = $roundGroup->sum(function ($manifest) use ($type) {
                            return $manifest->quantities->where('parcel_type_id', $type->id)->sum('re_manifested');
                        });
                        $totalCarriedForward = $roundGroup->sum(function ($manifest) use ($type) {
                            return $manifest->quantities->where('parcel_type_id', $type->id)->sum('carried_forward');
                        });
                        $total = $totalManifested + $totalReManifested + $totalCarriedForward;

                        // Calculate value based on the price for this round and parcel type
                        $price = DB::table('round_pricings')
                            ->where('round_id', $roundGroup->first()->round_id)
                            ->where('parcel_type_id', $type->id)
                            ->value('price') ?? 0;

                        return [
                            'parcel_type_id' => $type->id,
                            'name' => $type->name,
                            'manifested' => $totalManifested,
                            're_manifested' => $totalReManifested,
                            'carried_forward' => $totalCarriedForward,
                            'total' => $total,
                            'value' => $total * $price,
                        ];
                    });

                    $totalValue = $quantities->sum('value');
                    $round = $rounds->firstWhere('id', $roundId);

                    return [
                        'id' => $roundGroup->first()->id, // Use the first manifest ID for edit/delete actions
                        'delivery_date' => $date,
                        'round_id' => $round ? $round->round_id : 'Unknown',
                        'quantities' => $quantities,
                        'total_value' => $totalValue,
                    ];
                })->values();

                return [
                    'date' => $date,
                    'manifests' => $byRound,
                ];
            })->sortByDesc('date')->values();

            return [
                'period_name' => $periodName,
                'dates' => $byDate,
            ];
        })->sortByDesc('period_name')->values();

        $totalEarnings = $manifests->sum(function ($manifest) {
            $quantities = $manifest->quantities->map(function ($quantity) use ($manifest) {
                $price = DB::table('round_pricings')
                    ->where('round_id', $manifest->round_id)
                    ->where('parcel_type_id', $quantity->parcel_type_id)
                    ->value('price') ?? 0;
                $total = $quantity->manifested + $quantity->re_manifested + $quantity->carried_forward;
                return $total * $price;
            });
            return $quantities->sum();
        });

        return Inertia::render('Dashboard', [
            'groupedManifests' => $groupedManifests,
            'totalEarnings' => $totalEarnings,
            'rounds' => $rounds,
            'parcelTypes' => $parcelTypes,
            'flash' => session('flash', []),
        ]);
    }

     // Add this import at the top

public function store(Request $request)
{
    // Preload parcel types for validation
    $parcelTypes = ParcelType::pluck('id')->toArray();

    // Validate the request
    $validated = $request->validate([
        'delivery_date' => 'required|date',
        'status' => 'required|in:pending,in-progress,completed',
        'round_id' => ['required', Rule::exists('rounds', 'id')->where(function ($query) {
            $query->where('user_id', auth()->id());
        })],
        'quantities' => 'required|array',
        'quantities.*.parcel_type_id' => ['required', 'integer', Rule::in($parcelTypes)],
        'quantities.*.manifested' => 'required|integer|min:0',
        'quantities.*.re_manifested' => 'required|integer|min:0',
        'quantities.*.carried_forward' => 'required|integer|min:0',
    ]);

    DB::beginTransaction();
    try {
        // Create the manifest
        $manifest = Manifest::create([
            'delivery_date' => $validated['delivery_date'],
            'status' => $validated['status'],
            'round_id' => $validated['round_id'],
        ]);

        // Prepare data for bulk insert
        $quantityData = array_map(function ($quantity) use ($manifest) {
            return [
                'manifest_id' => $manifest->id,
                'parcel_type_id' => $quantity['parcel_type_id'],
                'manifested' => $quantity['manifested'],
                're_manifested' => $quantity['re_manifested'],
                'carried_forward' => $quantity['carried_forward'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $validated['quantities']);

        // Bulk insert quantities
        ManifestSummary::insert($quantityData);

        DB::commit();
        return redirect()->route('dashboard')->with('success', 'Manifest created successfully!');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error creating manifest', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return redirect()->route('dashboard')->with('error', 'Failed to create manifest: ' . $e->getMessage());
    }
}

    public function show($id)
{
    try {
        // Log the start of the method
        Log::info('Starting show method', ['id' => $id]);

        // Check if the user is authenticated
        if (!auth()->check()) {
            Log::error('User not authenticated');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        Log::info('User authenticated', ['user_id' => auth()->id()]);

        // Fetch the manifest
        $manifest = Manifest::with(['round', 'quantities'])->find($id);
        if (!$manifest) {
            Log::error('Manifest not found', ['id' => $id]);
            return response()->json(['error' => 'Manifest not found'], 404);
        }

        Log::info('Manifest found', ['manifest' => $manifest->toArray()]);

        // Check the round
        if (!$manifest->round) {
            Log::error('Round not found for manifest', ['id' => $id, 'round_id' => $manifest->round_id]);
            return response()->json(['error' => 'Associated round not found'], 404);
        }

        Log::info('Round found', ['round' => $manifest->round->toArray()]);

        // Check authorization
        if ($manifest->round->user_id !== auth()->id()) {
            Log::error('Unauthorized access to manifest', ['id' => $id, 'user_id' => auth()->id()]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Log::info('User authorized');

        // Process quantities
        $quantities = [];
        if ($manifest->quantities && !$manifest->quantities->isEmpty()) {
            $quantities = $manifest->quantities->map(function ($quantity) {
                Log::info('Mapping quantity', ['quantity' => $quantity->toArray()]);
                return [
                    'parcel_type_id' => (int) $quantity->parcel_type_id,
                    'manifested' => (int) ($quantity->manifested ?? 0),
                    're_manifested' => (int) ($quantity->re_manifested ?? 0),
                    'carried_forward' => (int) ($quantity->carried_forward ?? 0),
                ];
            })->toArray();
        } else {
            Log::warning('No quantities found for manifest', ['id' => $id]);
        }

        Log::info('Manifest fetched successfully', ['id' => $id]);
        return response()->json([
            'id' => $manifest->id,
            'delivery_date' => $manifest->delivery_date,
            'status' => $manifest->status,
            'round_id' => $manifest->round_id,
            'quantities' => $quantities,
        ], 200);
    } catch (\Exception $e) {
        Log::error('Error in show method', [
            'id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => 'Server error while fetching manifest' + $e], 500);
    }
}

    public function update(Request $request, $id)
    {
        $manifest = Manifest::findOrFail($id);

        if ($manifest->round->user_id !== auth()->id()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized to update this manifest.');
        }

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

        $round = Round::where('id', $validated['round_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$round) {
            return redirect()->route('dashboard')->with('error', 'Selected round does not belong to you.');
        }

        DB::beginTransaction();
        try {
            $manifest->update([
                'delivery_date' => $validated['delivery_date'],
                'status' => $validated['status'],
                'round_id' => $validated['round_id'],
            ]);

            // Delete existing quantities
            $manifest->quantities()->delete();

            // Create new quantities
            foreach ($validated['quantities'] as $quantity) {
                ManifestSummary::create([
                    'manifest_id' => $manifest->id,
                    'parcel_type_id' => $quantity['parcel_type_id'],
                    'manifested' => $quantity['manifested'],
                    're_manifested' => $quantity['re_manifested'],
                    'carried_forward' => $quantity['carried_forward'],
                ]);
            }

            DB::commit();
            return redirect()->route('dashboard')->with('success', 'Manifest updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('dashboard')->with('error', 'Failed to update manifest: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        Log::info('Deleting manifest with ID:', ['id' => $id]);
        $manifest = Manifest::findOrFail($id);

        if ($manifest->round->user_id !== auth()->id()) {
            Log::error('Unauthorized to delete manifest:', ['id' => $id, 'user_id' => auth()->id()]);
            return redirect()->route('dashboard')->with('error', 'Unauthorized to delete this manifest.');
        }

        $manifest->delete();

        Log::info('Manifest deleted successfully:', ['id' => $id]);
        return redirect()->route('dashboard')->with('success', 'Manifest deleted successfully!');
    }
}
