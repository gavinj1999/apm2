<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Round;
use App\Models\ParcelType;
use App\Models\ManifestSummary;
use App\Models\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ManifestController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();
            Log::info('User authenticated', ['user_id' => $user->id]);

            $rounds = Round::where('user_id', $user->id)->get();
            $roundIds = $rounds->pluck('id')->toArray();
            Log::info('User rounds fetched', ['user_id' => $user->id, 'round_ids' => $roundIds]);

            if (empty($roundIds)) {
                Log::warning('No rounds found for user', ['user_id' => $user->id]);
            }

            $parcelTypes = ParcelType::all();

            $manifests = Manifest::whereIn('round_id', $roundIds)
                ->with(['round', 'quantities'])
                ->get();

            Log::info('Manifests fetched for user', [
                'user_id' => $user->id,
                'manifest_count' => $manifests->count(),
                'manifest_round_ids' => $manifests->pluck('round_id')->unique()->toArray(),
            ]);

            $periods = Period::all();
            Log::info('Periods fetched', ['period_count' => $periods->count()]);

            $groupedManifests = $manifests->groupBy(function ($manifest) use ($periods) {
                $manifestDate = Carbon::parse($manifest->delivery_date);
                foreach ($periods as $period) {
                    $startDate = Carbon::parse($period->start_date);
                    $endDate = Carbon::parse($period->end_date);
                    if ($manifestDate->between($startDate, $endDate)) {
                        return $period->name;
                    }
                }
                return 'Unassigned';
            })->map(function ($group, $periodName) use ($rounds, $parcelTypes) {
                $byDate = $group->groupBy('delivery_date')->map(function ($dateGroup, $date) use ($rounds, $parcelTypes) {
                    $byRound = $dateGroup->groupBy('round_id')->map(function ($roundGroup, $roundId) use ($rounds, $parcelTypes, $date) {
                        $quantities = $parcelTypes->map(function ($type) use ($roundGroup) {
                            $totalManifested = 0;
                            $totalReManifested = 0;
                            $totalCarriedForward = 0;

                            try {
                                $totalManifested = $roundGroup->sum(function ($manifest) use ($type) {
                                    return $manifest->quantities->where('parcel_type_id', $type->id)->sum('manifested') ?? 0;
                                });
                                $totalReManifested = $roundGroup->sum(function ($manifest) use ($type) {
                                    return $manifest->quantities->where('parcel_type_id', $type->id)->sum('re_manifested') ?? 0;
                                });
                                $totalCarriedForward = $roundGroup->sum(function ($manifest) use ($type) {
                                    return $manifest->quantities->where('parcel_type_id', $type->id)->sum('carried_forward') ?? 0;
                                });
                            } catch (\Exception $e) {
                                Log::error('Error calculating quantities', [
                                    'round_id' => $roundGroup->first()->round_id,
                                    'parcel_type_id' => $type->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }

                            $total = $totalManifested + $totalReManifested + $totalCarriedForward;

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

                        return [
                            'id' => $roundGroup->first()->id,
                            'manifest_number' => $roundGroup->first()->manifest_number,
                            'delivery_date' => $date,
                            'round_id' => $roundId, // Use the round_id from the manifests table directly
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

            $currentDate = Carbon::today();
            Log::info('Current date', ['current_date' => $currentDate->toDateString()]);

            $currentPeriod = null;
            foreach ($periods as $period) {
                $startDate = Carbon::parse($period->start_date);
                $endDate = Carbon::parse($period->end_date);
                if ($currentDate->between($startDate, $endDate)) {
                    $currentPeriod = $period;
                    break;
                }
            }

            if (!$currentPeriod) {
                Log::warning('No current period found for today', ['current_date' => $currentDate->toDateString()]);
                $currentPeriodName = 'Unknown Period';
                $currentPeriodStart = $currentDate;
                $currentPeriodEnd = $currentDate;
            } else {
                $currentPeriodName = $currentPeriod->name;
                $currentPeriodStart = Carbon::parse($currentPeriod->start_date);
                $currentPeriodEnd = Carbon::parse($currentPeriod->end_date);
                Log::info('Current period calculated', [
                    'current_period' => $currentPeriodName,
                    'start_date' => $currentPeriodStart->toDateString(),
                    'end_date' => $currentPeriodEnd->toDateString(),
                ]);
            }

            $currentPeriodManifests = $manifests->filter(function ($manifest) use ($currentPeriodStart, $currentPeriodEnd) {
                $manifestDate = Carbon::parse($manifest->delivery_date);
                return $manifestDate->between($currentPeriodStart, $currentPeriodEnd);
            });

            Log::info('Current period manifests', [
                'count' => $currentPeriodManifests->count(),
                'manifest_round_ids' => $currentPeriodManifests->pluck('round_id')->unique()->toArray(),
            ]);

            $currentPeriodEarnings = (float) $currentPeriodManifests->sum(function ($manifest) {
                $quantitiesTotal = 0;
                try {
                    $quantitiesTotal = $manifest->quantities->sum(function ($quantity) use ($manifest) {
                        $price = DB::table('round_pricings')
                            ->where('round_id', $manifest->round_id)
                            ->where('parcel_type_id', $quantity->parcel_type_id)
                            ->value('price') ?? 0;
                        $quantityTotal = $quantity->manifested + $quantity->re_manifested + $quantity->carried_forward;
                        $value = $quantityTotal * $price;
                        Log::info('Calculating quantity value for manifest', [
                            'manifest_id' => $manifest->id,
                            'round_id' => $manifest->round_id,
                            'parcel_type_id' => $quantity->parcel_type_id,
                            'quantity_total' => $quantityTotal,
                            'price' => $price,
                            'value' => $value,
                        ]);
                        return $value;
                    });
                    Log::info('Total earnings for manifest', [
                        'manifest_id' => $manifest->id,
                        'quantities_total' => $quantitiesTotal,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error calculating total earnings for manifest', [
                        'manifest_id' => $manifest->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                return $quantitiesTotal;
            });

            $daysWithManifests = $currentPeriodManifests->groupBy('delivery_date')->count();
            $averageDailyIncome = $daysWithManifests > 0 ? (float) ($currentPeriodEarnings / $daysWithManifests) : 0.0;

            $remainingDays = $currentDate->diffInDays($currentPeriodEnd);

            Log::info('Dashboard metrics calculated', [
                'currentPeriod' => $currentPeriodName,
                'currentPeriodEarnings' => $currentPeriodEarnings,
                'averageDailyIncome' => $averageDailyIncome,
                'remainingDays' => $remainingDays,
            ]);

            return Inertia::render('Dashboard', [
                'groupedManifests' => $groupedManifests,
                'currentPeriodEarnings' => $currentPeriodEarnings,
                'averageDailyIncome' => $averageDailyIncome,
                'remainingDays' => $remainingDays,
                'currentPeriod' => $currentPeriodName,
                'rounds' => $rounds,
                'parcelTypes' => $parcelTypes,
                'flash' => session('flash', []),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in index method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('dashboard')->with('error', 'An error occurred while loading the dashboard.');
        }
    }

    public function store(Request $request)
    {
        Log::info('Storing new manifest', ['user_id' => auth()->id()]);

        $parcelTypes = Cache::remember('parcel_types_ids', 60 * 60 * 24, function () {
            return ParcelType::pluck('id')->toArray();
        });

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
            $manifestNumber = 'MAN-' . now()->format('Ymd') . '-' . Str::random(8);
            Log::info('Generating manifest number', ['manifest_number' => $manifestNumber]);

            $manifest = Manifest::create([
                'manifest_number' => $manifestNumber,
                'delivery_date' => $validated['delivery_date'],
                'status' => $validated['status'],
                'round_id' => $validated['round_id'],
            ]);

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

            ManifestSummary::insert($quantityData);

            DB::commit();
            Log::info('Manifest created successfully', ['manifest_id' => $manifest->id]);

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
            Log::info('Starting show method', ['id' => $id]);

            if (!auth()->check()) {
                Log::error('User not authenticated');
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            Log::info('User authenticated', ['user_id' => auth()->id()]);

            $manifest = Manifest::with(['round', 'quantities'])->find($id);
            if (!$manifest) {
                Log::error('Manifest not found', ['id' => $id]);
                return response()->json(['error' => 'Manifest not found'], 404);
            }

            Log::info('Manifest found', ['manifest' => $manifest->toArray()]);

            if (!$manifest->round) {
                Log::error('Round not found for manifest', ['id' => $id, 'round_id' => $manifest->round_id]);
                return response()->json(['error' => 'Associated round not found'], 404);
            }

            Log::info('Round found', ['round' => $manifest->round->toArray()]);

            if ($manifest->round->user_id !== auth()->id()) {
                Log::error('Unauthorized access to manifest', ['id' => $id, 'user_id' => auth()->id()]);
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            Log::info('User authorized');

            $quantities = [];
            try {
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
            } catch (\Exception $e) {
                Log::error('Error processing quantities', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $quantities = [];
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
            return response()->json(['error' => 'Server error while fetching manifest'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        Log::info('Updating manifest', ['id' => $id, 'user_id' => auth()->id()]);

        $manifest = Manifest::findOrFail($id);

        if ($manifest->round->user_id !== auth()->id()) {
            Log::error('Unauthorized to update manifest', ['id' => $id, 'user_id' => auth()->id()]);
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
            Log::error('Selected round does not belong to user', ['round_id' => $validated['round_id'], 'user_id' => auth()->id()]);
            return redirect()->route('dashboard')->with('error', 'Selected round does not belong to you.');
        }

        DB::beginTransaction();
        try {
            $manifest->update([
                'delivery_date' => $validated['delivery_date'],
                'status' => $validated['status'],
                'round_id' => $validated['round_id'],
            ]);

            $manifest->quantities()->delete();

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
            Log::info('Manifest updated successfully', ['manifest_id' => $manifest->id]);

            return redirect()->route('dashboard')->with('success', 'Manifest updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating manifest', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('dashboard')->with('error', 'Failed to update manifest: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        Log::info('Deleting manifest with ID:', ['id' => $id, 'user_id' => auth()->id()]);

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
