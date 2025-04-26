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
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ManifestController extends Controller
{
    public function index()
    {

        $debug = []; // Initialize debug array

        try {
            $user = auth()->user();
            $debug['user_id'] = $user->id;

            $rounds = Round::where('user_id', $user->id)->get();
            $roundIds = $rounds->pluck('id')->toArray();
            $debug['roundIds'] = $roundIds;

            $parcelTypes = ParcelType::all();
            $debug['parcelTypesCount'] = $parcelTypes->count();

            $manifests = Manifest::whereIn('round_id', $roundIds)
                ->with(['round', 'quantities'])
                ->get();
            $debug['manifestsCount'] = $manifests->count();

            $periods = Period::all();
            $debug['periods'] = $periods->toArray();

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
                                // Silent catch
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
                            'round_id' => $roundId,
                            'quantities' => $quantities->toArray(),
                            'total_value' => $totalValue,
                        ];
                    })->values();

                    return [
                        'date' => $date,
                        'manifests' => $byRound->toArray(),
                    ];
                })->sortByDesc('date')->values();

                return [
                    'period_name' => $periodName,
                    'dates' => $byDate->toArray(),
                ];
            })->sortByDesc('period_name')->values();

            $currentDate = Carbon::today();
            $debug['currentDate'] = $currentDate->toDateString();

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
                $currentPeriodName = 'Unknown Period';
                $currentPeriodStart = $currentDate;
                $currentPeriodEnd = $currentDate;
            } else {
                $currentPeriodName = $currentPeriod->name;
                $currentPeriodStart = Carbon::parse($currentPeriod->start_date);
                $currentPeriodEnd = Carbon::parse($currentPeriod->end_date);
            }

            $debug['currentPeriodName'] = $currentPeriodName;
            $debug['currentPeriodStart'] = $currentPeriodStart->toDateString();
            $debug['currentPeriodEnd'] = $currentPeriodEnd->toDateString();

            $currentPeriodManifests = $manifests->filter(function ($manifest) use ($currentPeriodStart, $currentPeriodEnd) {
                $manifestDate = Carbon::parse($manifest->delivery_date);
                return $manifestDate->between($currentPeriodStart, $currentPeriodEnd);
            });

            $debug['manifestCount'] = $currentPeriodManifests->count();
            $debug['manifestDates'] = $currentPeriodManifests->pluck('delivery_date')->toArray();

            // Calculate total earnings and days with manifests using SQL
            try {
                $earningsResult = DB::selectOne("
                    SELECT
                        COUNT(DISTINCT m.delivery_date) AS days_with_manifests,
                        SUM(
                            (ms.manifested + ms.re_manifested + ms.carried_forward) *
                            COALESCE(rp.price, 0)
                        ) AS total_earnings
                    FROM manifests m
                    LEFT JOIN manifest_summaries ms ON m.id = ms.manifest_id
                    LEFT JOIN round_pricings rp
                        ON m.round_id = rp.round_id
                        AND ms.parcel_type_id = rp.parcel_type_id
                    WHERE m.delivery_date BETWEEN ? AND ?
                    AND m.round_id IN (SELECT id FROM rounds WHERE user_id = ?)
                ", [$currentPeriodStart, $currentPeriodEnd, $user->id]);

                $debug['earningsResult'] = (array) $earningsResult;
            } catch (\Exception $e) {
                $debug['earningsQueryError'] = $e->getMessage();
                $earningsResult = null;
            }

            $currentPeriodEarnings = (float) ($earningsResult->total_earnings ?? 0.0);
            $daysWithManifests = (int) ($earningsResult->days_with_manifests ?? 0);
            $averageDailyIncome = $daysWithManifests > 0 ? (float) ($currentPeriodEarnings / $daysWithManifests) : 0.0;

            $debug['currentPeriodEarnings'] = $currentPeriodEarnings;
            $debug['daysWithManifests'] = $daysWithManifests;
            $debug['averageDailyIncome'] = $averageDailyIncome;

            // Calculate remaining days using SQL
            try {
                $remainingDaysResult = DB::selectOne("
                    SELECT
                        GREATEST(
                            DATEDIFF(
                                (SELECT end_date
                                 FROM periods
                                 WHERE start_date <= ?
                                   AND end_date >= ?),
                                ?
                            ),
                            0
                        ) AS days_remaining
                ", [$currentDate->toDateString(), $currentDate->toDateString(), $currentDate->toDateString()]);

                $remainingDays = $remainingDaysResult ? (int) $remainingDaysResult->days_remaining : 0;
                $debug['remainingDays'] = $remainingDays;
            } catch (\Exception $e) {
                $debug['remainingDaysQueryError'] = $e->getMessage();
                $remainingDays = 0;
            }

            return Inertia::render('Dashboard', [
                'groupedManifests' => $groupedManifests->toArray(),
                'currentPeriodEarnings' => $currentPeriodEarnings,
                'averageDailyIncome' => $averageDailyIncome,
                'remainingDays' => $remainingDays,
                'currentPeriod' => $currentPeriodName,
                'rounds' => $rounds->toArray(),
                'parcelTypes' => $parcelTypes->toArray(),
                'flash' => session('flash', []),
                'debug' => $debug,
            ]);
        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
            $debug['errorTrace'] = $e->getTraceAsString();

            return Inertia::render('Dashboard', [
                'groupedManifests' => [],
                'currentPeriodEarnings' => 0.0,
                'averageDailyIncome' => 0.0,
                'remainingDays' => 0,
                'currentPeriod' => 'Error',
                'rounds' => [],
                'parcelTypes' => [],
                'flash' => session('flash', []),
                'debug' => $debug,
            ]);
        }
    }

    public function store(Request $request)
    {
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

            return redirect()->route('dashboard')->with('success', 'Manifest created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('dashboard')->with('error', 'Failed to create manifest: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            if (!auth()->check()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $manifest = Manifest::with(['round', 'quantities'])->find($id);
            if (!$manifest) {
                return response()->json(['error' => 'Manifest not found'], 404);
            }

            if (!$manifest->round) {
                return response()->json(['error' => 'Associated round not found'], 404);
            }

            if ($manifest->round->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $quantities = [];
            try {
                if ($manifest->quantities && !$manifest->quantities->isEmpty()) {
                    $quantities = $manifest->quantities->map(function ($quantity) {
                        return [
                            'parcel_type_id' => (int) $quantity->parcel_type_id,
                            'manifested' => (int) ($quantity->manifested ?? 0),
                            're_manifested' => (int) ($quantity->re_manifested ?? 0),
                            'carried_forward' => (int) ($quantity->carried_forward ?? 0),
                        ];
                    })->toArray();
                }
            } catch (\Exception $e) {
                $quantities = [];
            }

            return response()->json([
                'id' => $manifest->id,
                'delivery_date' => $manifest->delivery_date,
                'status' => $manifest->status,
                'round_id' => $manifest->round_id,
                'quantities' => $quantities,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error while fetching manifest'], 500);
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

            return redirect()->route('dashboard')->with('success', 'Manifest updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('dashboard')->with('error', 'Failed to create manifest: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $manifest = Manifest::findOrFail($id);

        if ($manifest->round->user_id !== auth()->id()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized to delete this manifest.');
        }

        $manifest->delete();

        return redirect()->route('dashboard')->with('success', 'Manifest deleted successfully!');
    }
}
