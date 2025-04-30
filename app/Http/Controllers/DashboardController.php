<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Period;
use App\Models\Round;
use App\Models\ParcelType;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Holiday;

class DashboardController extends Controller
{
    public function index()
{
    $debug = [];

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

        $periods = Period::orderBy('start_date', 'desc')->get();
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

                    // Find the round name from the rounds collection
                    $round = $rounds->firstWhere('id', $roundId);

                    return [
                        'id' => $roundGroup->first()->id,
                        'manifest_number' => $roundGroup->first()->manifest_number,
                        'delivery_date' => $date,
                        'round_id' => $roundId,
                        'round_name' => $round ? $round->round_id : 'Unknown Round',
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

        // Select the most recent period if the current date isn't in any period
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
            // Fallback to the most recent period
            $currentPeriod = $periods->first();
            if ($currentPeriod) {
                $currentPeriodName = $currentPeriod->name;
                $currentPeriodStart = Carbon::parse($currentPeriod->start_date);
                $currentPeriodEnd = Carbon::parse($currentPeriod->end_date);
            } else {
                $currentPeriodName = 'Unknown Period';
                $currentPeriodStart = $currentDate->copy()->subDays(30); // Default to last 30 days
                $currentPeriodEnd = $currentDate;
            }
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

        try {
            $remainingDaysResult = DB::selectOne("
                SELECT
                    GREATEST(
                        DATEDIFF(
                            ?,
                            ?
                        ),
                        0
                    ) AS days_remaining
            ", [$currentPeriodEnd->toDateString(), $currentDate->toDateString()]);

            $remainingDays = $remainingDaysResult ? (int) $remainingDaysResult->days_remaining : 0;
            $debug['remainingDays'] = $remainingDays;
        } catch (\Exception $e) {
            $debug['remainingDaysQueryError'] = $e->getMessage();
            $remainingDays = 0;
        }

        $holidays = Holiday::all()->toArray();

        return Inertia::render('Dashboard', [
            'groupedManifests' => $groupedManifests->toArray(),
            'currentPeriodEarnings' => $currentPeriodEarnings,
            'averageDailyIncome' => $averageDailyIncome,
            'remainingDays' => $remainingDays,
            'currentPeriod' => $currentPeriodName,
            'rounds' => $rounds->toArray(),
            'parcelTypes' => $parcelTypes->toArray(),
            'holidays' => $holidays,
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

public function downloadCsv()
{
    $user = auth()->user();
    $rounds = Round::where('user_id', $user->id)->get();
    $roundIds = $rounds->pluck('id')->toArray();
    $parcelTypes = ParcelType::all();

    $manifests = Manifest::whereIn('round_id', $roundIds)
        ->with(['round', 'quantities'])
        ->get();

    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="manifests.csv"',
    ];

    $callback = function () use ($manifests, $parcelTypes) {
        $file = fopen('php://output', 'w');
        // Write CSV headers
        $columns = ['Date', 'Round', ...$parcelTypes->pluck('name')->toArray(), 'Total Value'];
        fputcsv($file, $columns);

        // Write data
        foreach ($manifests as $manifest) {
            $row = [
                $manifest->delivery_date,
                $manifest->round->round_id ?? 'Unknown Round',
            ];
            foreach ($parcelTypes as $type) {
                $quantity = $manifest->quantities->where('parcel_type_id', $type->id)->first();
                $total = $quantity ? ($quantity->manifested + $quantity->re_manifested + $quantity->carried_forward) : 0;
                $row[] = $total;
            }
            $totalValue = $manifest->quantities->sum(function ($q) use ($manifest) {
                $price = DB::table('round_pricings')
                    ->where('round_id', $manifest->round_id)
                    ->where('parcel_type_id', $q->parcel_type_id)
                    ->value('price') ?? 0;
                return ($q->manifested + $q->re_manifested + $q->carried_forward) * $price;
            });
            $row[] = $totalValue;
            fputcsv($file, $row);
        }

        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
}
}
