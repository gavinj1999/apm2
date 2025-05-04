<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Period;
use App\Models\Round;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $latestPeriod = Period::orderBy('end_date', 'desc')->first();
        $defaultPeriodId = $latestPeriod ? $latestPeriod->id : null;
        $selectedPeriodIds = $request->input('periods', $defaultPeriodId ? [$defaultPeriodId] : []);
        if (!is_array($selectedPeriodIds)) {
            $selectedPeriodIds = [$selectedPeriodIds];
        }

        $availablePeriods = Period::orderBy('start_date', 'desc')
            ->get()
            ->mapWithKeys(function ($period) {
                return [$period->id => $period->name];
            })->toArray();

        $selectedPeriodIds = array_filter($selectedPeriodIds, fn($id) => array_key_exists($id, $availablePeriods));

        if (empty($selectedPeriodIds) && $defaultPeriodId) {
            $selectedPeriodIds = [$defaultPeriodId];
        }

        $selectedPeriods = Period::whereIn('id', $selectedPeriodIds)->get();
        $allPeriods = Period::orderBy('start_date', 'asc')->get();

        $rounds = Round::where('user_id', auth()->id())->get();

        // Fetch manifests for the user with their quantities and parcel types
        $manifests = Manifest::where('user_id', auth()->id())
            ->with(['round', 'quantities.parcelType'])
            ->get();

        // Debug: Log the raw data
        \Log::info('All Periods:', $allPeriods->toArray());
        \Log::info('Selected Periods:', $selectedPeriods->toArray());
        \Log::info('Rounds:', $rounds->toArray());
        \Log::info('Manifests:', $manifests->toArray());

        // Filter manifests by the selected periods' date ranges for summary and pie chart
        $filteredManifests = $manifests->filter(function ($manifest) use ($selectedPeriods) {
            return $selectedPeriods->contains(function ($period) use ($manifest) {
                $deliveryDate = new \DateTime($manifest->delivery_date);
                $startDate = new \DateTime($period->start_date);
                $endDate = new \DateTime($period->end_date);
                return $deliveryDate >= $startDate && $deliveryDate <= $endDate;
            });
        });

        // Debug: Log filtered manifests
        \Log::info('Filtered Manifests:', $filteredManifests->toArray());

        // Calculate total summary across all rounds and selected periods
        $totalParcels = $filteredManifests->sum(function ($manifest) {
            return $manifest->quantities->sum('total_parcels');
        });

        $totalIncome = $filteredManifests->sum(function ($manifest) use ($rounds) {
            $round = $rounds->firstWhere('id', $manifest->round_id);
            if (!$round) return 0;
            return $manifest->quantities->sum(function ($quantity) use ($round) {
                $rate = $round->parcelTypes()
                    ->where('parcel_types.id', $quantity->parcel_type_id)
                    ->first()
                    ?->pivot
                    ->price ?? 0;
                return $quantity->total_parcels * $rate;
            });
        });

        $totalPacketTypeData = $filteredManifests->flatMap->quantities
            ->groupBy('parcel_type_id')
            ->map(function ($quantities, $parcelTypeId) use ($totalParcels, $rounds) {
                $count = $quantities->sum('total_parcels');
                $parcelType = $quantities->first()->parcelType;
                $rate = 0;
                foreach ($quantities as $quantity) {
                    $round = $rounds->firstWhere('id', $quantity->manifest->round_id);
                    if ($round) {
                        $rate = $round->parcelTypes()
                            ->where('parcel_types.id', $parcelTypeId)
                            ->first()
                            ?->pivot
                            ->price ?? 0;
                        break;
                    }
                }
                $income = $count * $rate;
                $percentage = $totalParcels ? ($count / $totalParcels * 100) : 0;

                return [
                    'name' => $parcelType ? $parcelType->name : 'Unknown',
                    'total' => $count,
                    'income' => $income,
                    'percentage' => $percentage,
                ];
            })->values()->toArray();

        // Debug: Log total packet type data
        \Log::info('Total Packet Type Data:', $totalPacketTypeData);

        $totalSummary = [
            'name' => 'Total Summary',
            'period_name' => $selectedPeriods->count() > 1 ? 'Selected Periods' : ($selectedPeriods->first()->name ?? 'All Periods'),
            'total_parcels' => $totalParcels,
            'total_income' => $totalIncome,
            'packet_types' => $totalPacketTypeData,
        ];

        // Group data by round and period for the summaries
        $reportData = $rounds->flatMap(function ($round) use ($filteredManifests, $selectedPeriods) {
            return $selectedPeriods->map(function ($period) use ($round, $filteredManifests) {
                $roundManifests = $filteredManifests->filter(function ($manifest) use ($round, $period) {
                    $deliveryDate = new \DateTime($manifest->delivery_date);
                    $startDate = new \DateTime($period->start_date);
                    $endDate = new \DateTime($period->end_date);
                    return $manifest->round_id === $round->id &&
                           $deliveryDate >= $startDate &&
                           $deliveryDate <= $endDate;
                });

                $totalParcels = $roundManifests->sum(function ($manifest) {
                    return $manifest->quantities->sum('total_parcels');
                });

                if ($totalParcels === 0) {
                    return null; // Skip if no parcels
                }

                $totalIncome = $roundManifests->sum(function ($manifest) use ($round) {
                    return $manifest->quantities->sum(function ($quantity) use ($round) {
                        $rate = $round->parcelTypes()
                            ->where('parcel_types.id', $quantity->parcel_type_id)
                            ->first()
                            ?->pivot
                            ->price ?? 0;
                        return $quantity->total_parcels * $rate;
                    });
                });

                $packetTypeData = $roundManifests->flatMap->quantities
                    ->groupBy('parcel_type_id')
                    ->map(function ($quantities, $parcelTypeId) use ($totalParcels, $round) {
                        $count = $quantities->sum('total_parcels');
                        $parcelType = $quantities->first()->parcelType;
                        $rate = $round->parcelTypes()
                            ->where('parcel_types.id', $parcelTypeId)
                            ->first()
                            ?->pivot
                            ->price ?? 0;
                        $income = $count * $rate;
                        $percentage = $totalParcels ? ($count / $totalParcels * 100) : 0;

                        return [
                            'name' => $parcelType ? $parcelType->name : 'Unknown',
                            'total' => $count,
                            'income' => $income,
                            'percentage' => $percentage,
                        ];
                    })->values()->toArray();

                return [
                    'round_id' => $round->id,
                    'period_id' => $period->id,
                    'period_name' => $period->name,
                    'name' => $round->name ?? $round->round_id,
                    'total_parcels' => $totalParcels,
                    'total_income' => $totalIncome,
                    'packet_types' => $packetTypeData,
                ];
            })->filter();
        })->values()->toArray();

        // Prepare data for the income summary bar chart (all periods)
        $incomeByPeriod = $allPeriods->map(function ($period) use ($manifests, $rounds) {
            $periodManifests = $manifests->filter(function ($manifest) use ($period) {
                $deliveryDate = new \DateTime($manifest->delivery_date);
                $startDate = new \DateTime($period->start_date);
                $endDate = new \DateTime($period->end_date);
                return $deliveryDate >= $startDate && $deliveryDate <= $endDate;
            });

            $income = $periodManifests->sum(function ($manifest) use ($rounds) {
                $round = $rounds->firstWhere('id', $manifest->round_id);
                if (!$round) return 0;
                return $manifest->quantities->sum(function ($quantity) use ($round) {
                    $rate = $round->parcelTypes()
                        ->where('parcel_types.id', $quantity->parcel_type_id)
                        ->first()
                        ?->pivot
                        ->price ?? 0;
                    return $quantity->total_parcels * $rate;
                });
            });

            return [
                'period_name' => $period->name,
                'income' => $income,
            ];
        })->toArray();

        // Debug: Log income by period
        \Log::info('Income by Period:', $incomeByPeriod);

        // Prepare data for the parcel type pie chart (based on selected periods)
        $pieChartData = [
            'labels' => array_column($totalPacketTypeData, 'name') ?: [],
            'data' => array_column($totalPacketTypeData, 'total') ?: [],
        ];

        // Debug: Log pie chart data
        \Log::info('Pie Chart Data:', $pieChartData);

        return Inertia::render('Reports/Index', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriods' => $selectedPeriodIds,
            'totalSummary' => $totalSummary,
            'reportData' => $reportData,
            'incomeByPeriod' => $incomeByPeriod ?: [],
            'pieChartData' => $pieChartData,
            'flash' => session('flash', []),
        ]);
    }
}
