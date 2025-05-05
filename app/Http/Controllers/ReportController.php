<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Period;
use App\Models\Round;
use App\Models\ServiceProfile;
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

        // Fetch the service profile for the user
        $serviceProfile = ServiceProfile::where('user_id', auth()->id())->first();

        // Filter manifests by the selected periods' date ranges for summary and pie chart
        $filteredManifests = $manifests->filter(function ($manifest) use ($selectedPeriods) {
            if (!$manifest->delivery_date) {
                \Log::warning('Manifest has no delivery_date:', ['manifest_id' => $manifest->id]);
                return false;
            }

            try {
                $deliveryDate = new \DateTime($manifest->delivery_date);
            } catch (\Exception $e) {
                \Log::error('Invalid delivery_date for manifest:', [
                    'manifest_id' => $manifest->id,
                    'delivery_date' => $manifest->delivery_date,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            return $selectedPeriods->contains(function ($period) use ($deliveryDate) {
                try {
                    $startDate = new \DateTime($period->start_date);
                    $endDate = new \DateTime($period->end_date);
                } catch (\Exception $e) {
                    \Log::error('Invalid period dates:', [
                        'period_id' => $period->id,
                        'start_date' => $period->start_date,
                        'end_date' => $period->end_date,
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                }

                $isWithinRange = $deliveryDate >= $startDate && $deliveryDate <= $endDate;
                // \Log::debug('Checking manifest date range:', [
                //     'manifest_id' => $manifest->id,
                //     'delivery_date' => $deliveryDate->format('Y-m-d'),
                //     'period_id' => $period->id,
                //     'start_date' => $startDate->format('Y-m-d'),
                //     'end_date' => $endDate->format('Y-m-d'),
                //     'is_within_range' => $isWithinRange,
                // ]);
                return $isWithinRange;
            });
        });

        // Debug: Log filtered manifests
        \Log::info('Filtered Manifests:', $filteredManifests->toArray());

        // Calculate total summary across all rounds and selected periods
        $totalParcels = $filteredManifests->sum(function ($manifest) {
            $total = $manifest->quantities->sum('total_parcels');
            \Log::debug('Calculating total parcels for manifest:', [
                'manifest_id' => $manifest->id,
                'total_parcels' => $total,
            ]);
            return $total;
        });

        $totalIncome = $filteredManifests->sum(function ($manifest) use ($rounds) {
            $round = $rounds->firstWhere('id', $manifest->round_id);
            if (!$round) {
                \Log::warning('No round found for manifest:', ['manifest_id' => $manifest->id, 'round_id' => $manifest->round_id]);
                return 0;
            }
            $income = $manifest->quantities->sum(function ($quantity) use ($round) {
                $parcelType = $round->parcelTypes()
                    ->where('parcel_types.id', $quantity->parcel_type_id)
                    ->first();
                if (!$parcelType) {
                    \Log::warning('No parcel type found for quantity:', [
                        'quantity_id' => $quantity->id,
                        'parcel_type_id' => $quantity->parcel_type_id,
                        'round_id' => $round->id,
                    ]);
                    return 0;
                }
                $rate = $parcelType->pivot->price ?? 0;
                \Log::debug('Calculating income for quantity:', [
                    'quantity_id' => $quantity->id,
                    'parcel_type_id' => $quantity->parcel_type_id,
                    'total_parcels' => $quantity->total_parcels,
                    'rate' => $rate,
                    'income' => $quantity->total_parcels * $rate,
                ]);
                return $quantity->total_parcels * $rate;
            });
            return $income;
        });

        // Calculate total costs for selected periods
        $totalFuelCost = 0;
        $totalLoadingCost = 0;
        $totalCost = 0;
        $totalProfit = 0;

        if ($serviceProfile) {
            $totalDistance = $serviceProfile->distance_to_location + $serviceProfile->distance_from_location;
            $totalFuelCost = $totalDistance * $serviceProfile->fuel_cost_per_unit;
            $totalLoadingCost = $serviceProfile->loading_time_hours * $serviceProfile->loading_time_cost_per_hour;
            $totalCost = $totalFuelCost + $totalLoadingCost;
            $totalProfit = $totalIncome - $totalCost;

            \Log::info('Profitability Calculation:', [
                'total_income' => $totalIncome,
                'total_fuel_cost' => $totalFuelCost,
                'total_loading_cost' => $totalLoadingCost,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
            ]);
        }

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
                        \Log::debug('Rate for parcel type in total summary:', [
                            'parcel_type_id' => $parcelTypeId,
                            'round_id' => $round->id,
                            'rate' => $rate,
                        ]);
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
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'packet_types' => $totalPacketTypeData,
        ];

        // Group data by round and period for the summaries
        $reportData = $rounds->flatMap(function ($round) use ($filteredManifests, $selectedPeriods, $serviceProfile) {
            return $selectedPeriods->map(function ($period) use ($round, $filteredManifests, $serviceProfile) {
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
                    \Log::debug('No parcels for round and period:', [
                        'round_id' => $round->id,
                        'period_id' => $period->id,
                    ]);
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

                // Calculate costs for this round (if service profile is tied to a round)
                $roundCost = 0;
                $roundProfit = 0;
                if ($serviceProfile && (!$serviceProfile->round_id || $serviceProfile->round_id === $round->id)) {
                    $totalDistance = $serviceProfile->distance_to_location + $serviceProfile->distance_from_location;
                    $fuelCost = $totalDistance * $serviceProfile->fuel_cost_per_unit;
                    $loadingCost = $serviceProfile->loading_time_hours * $serviceProfile->loading_time_cost_per_hour;
                    $roundCost = $fuelCost + $loadingCost;
                    $roundProfit = $totalIncome - $roundCost;
                }

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
                    'total_cost' => $roundCost,
                    'total_profit' => $roundProfit,
                    'packet_types' => $packetTypeData,
                ];
            })->filter();
        })->values()->toArray();

        // Prepare data for the income summary bar chart (all periods)
        $incomeByPeriod = $allPeriods->map(function ($period) use ($manifests, $rounds, $serviceProfile) {
            $periodManifests = $manifests->filter(function ($manifest) use ($period) {
                if (!$manifest->delivery_date) {
                    \Log::warning('Manifest has no delivery_date in income by period:', ['manifest_id' => $manifest->id]);
                    return false;
                }

                try {
                    $deliveryDate = new \DateTime($manifest->delivery_date);
                } catch (\Exception $e) {
                    \Log::error('Invalid delivery_date for manifest in income by period:', [
                        'manifest_id' => $manifest->id,
                        'delivery_date' => $manifest->delivery_date,
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                }

                try {
                    $startDate = new \DateTime($period->start_date);
                    $endDate = new \DateTime($period->end_date);
                } catch (\Exception $e) {
                    \Log::error('Invalid period dates in income by period:', [
                        'period_id' => $period->id,
                        'start_date' => $period->start_date,
                        'end_date' => $period->end_date,
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                }

                return $deliveryDate >= $startDate && $deliveryDate <= $endDate;
            });

            $income = $periodManifests->sum(function ($manifest) use ($rounds) {
                $round = $rounds->firstWhere('id', $manifest->round_id);
                if (!$round) {
                    \Log::warning('No round found for manifest in income by period:', ['manifest_id' => $manifest->id, 'round_id' => $manifest->round_id]);
                    return 0;
                }
                $income = $manifest->quantities->sum(function ($quantity) use ($round) {
                    $parcelType = $round->parcelTypes()
                        ->where('parcel_types.id', $quantity->parcel_type_id)
                        ->first();
                    if (!$parcelType) {
                        \Log::warning('No parcel type found for quantity in income by period:', [
                            'quantity_id' => $quantity->id,
                            'parcel_type_id' => $quantity->parcel_type_id,
                            'round_id' => $round->id,
                        ]);
                        return 0;
                    }
                    $rate = $parcelType->pivot->price ?? 0;
                    \Log::debug('Income by period calculation:', [
                        'manifest_id' => $quantity->manifest_id,
                        'quantity_id' => $quantity->id,
                        'parcel_type_id' => $quantity->parcel_type_id,
                        'total_parcels' => $quantity->total_parcels,
                        'rate' => $rate,
                        'income' => $quantity->total_parcels * $rate,
                    ]);
                    return $quantity->total_parcels * $rate;
                });
                return $income;
            });

            // Calculate profit for this period
            $periodCost = 0;
            $periodProfit = 0;
            if ($serviceProfile) {
                $totalDistance = $serviceProfile->distance_to_location + $serviceProfile->distance_from_location;
                $fuelCost = $totalDistance * $serviceProfile->fuel_cost_per_unit;
                $loadingCost = $serviceProfile->loading_time_hours * $serviceProfile->loading_time_cost_per_hour;
                $periodCost = $fuelCost + $loadingCost;
                $periodProfit = $income - $periodCost;
            }

            return [
                'period_name' => $period->name,
                'income' => $income,
                'cost' => $periodCost,
                'profit' => $periodProfit,
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
