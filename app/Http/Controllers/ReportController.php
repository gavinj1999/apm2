<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Period;
use App\Models\Round;
use App\Models\ServiceProfile;
use App\Models\ParcelType;
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
        $parcelTypes = ParcelType::all();

        // Fetch manifests for the user with their quantities and parcel types
        $manifests = Manifest::where('user_id', auth()->id())
            ->with(['round', 'quantities.parcelType'])
            ->get();

        // Fetch the service profile for the user
        $serviceProfile = ServiceProfile::where('user_id', auth()->id())->first();

        // Filter manifests by the selected periods' date ranges and additional filters
        $filteredManifests = $manifests->filter(function ($manifest) use ($selectedPeriods, $request) {
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

            // Period filter
            $isWithinPeriod = $selectedPeriods->contains(function ($period) use ($deliveryDate) {
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
                return $deliveryDate >= $startDate && $deliveryDate <= $endDate;
            });

            if (!$isWithinPeriod) {
                return false;
            }

            // Round filter
            if ($request->round_id && $manifest->round_id != $request->round_id) {
                return false;
            }

            // Parcel type filter
            if ($request->parcel_type_id) {
                return $manifest->quantities->contains('parcel_type_id', $request->parcel_type_id);
            }

            return true;
        });

        // Calculate total summary across all rounds and selected periods
        $totalParcels = $filteredManifests->sum(function ($manifest) {
            return $manifest->quantities->sum('total_parcels');
        });

        $totalIncome = $filteredManifests->sum(function ($manifest) use ($rounds) {
            $round = $rounds->firstWhere('id', $manifest->round_id);
            if (!$round) {
                \Log::warning('No round found for manifest:', ['manifest_id' => $manifest->id, 'round_id' => $manifest->round_id]);
                return 0;
            }
            return $manifest->quantities->sum(function ($quantity) use ($round) {
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
                return $quantity->total_parcels * $rate;
            });
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
        $reportData = $rounds->flatMap(function ($round) use ($filteredManifests, $selectedPeriods, $serviceProfile, $request) {
            return $selectedPeriods->map(function ($period) use ($round, $filteredManifests, $serviceProfile, $request) {
                $roundManifests = $filteredManifests->filter(function ($manifest) use ($round, $period, $request) {
                    $deliveryDate = new \DateTime($manifest->delivery_date);
                    $startDate = new \DateTime($period->start_date);
                    $endDate = new \DateTime($period->end_date);
                    return $manifest->round_id === $round->id &&
                           $deliveryDate >= $startDate &&
                           $deliveryDate <= $endDate &&
                           (!$request->parcel_type_id || $manifest->quantities->contains('parcel_type_id', $request->parcel_type_id));
                });

                $totalParcels = $roundManifests->sum(function ($manifest) {
                    return $manifest->quantities->sum('total_parcels');
                });

                if ($totalParcels === 0) {
                    return null;
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

        // Delivery performance data
        $deliveryPerformance = $filteredManifests->flatMap(function ($manifest) use ($rounds, $serviceProfile, $request) {
            $round = $rounds->firstWhere('id', $manifest->round_id);
            if (!$round) {
                return [];
            }

            $cost = 0;
            if ($serviceProfile && (!$serviceProfile->round_id || $serviceProfile->round_id === $round->id)) {
                $totalDistance = $serviceProfile->distance_to_location + $serviceProfile->distance_from_location;
                $fuelCost = $totalDistance * $serviceProfile->fuel_cost_per_unit;
                $loadingCost = $serviceProfile->loading_time_hours * $serviceProfile->loading_time_cost_per_hour;
                $cost = $fuelCost + $loadingCost;
            }

            return $manifest->quantities
                ->filter(function ($quantity) use ($request) {
                    return !$request->parcel_type_id || $quantity->parcel_type_id == $request->parcel_type_id;
                })
                ->map(function ($quantity) use ($manifest, $round, $cost) {
                    $rate = $round->parcelTypes()
                        ->where('parcel_types.id', $quantity->parcel_type_id)
                        ->first()
                        ?->pivot
                        ->price ?? 0;
                    $income = $quantity->total_parcels * $rate;
                    $profit = $income - $cost;

                    return [
                        'date' => $manifest->delivery_date,
                        'round_name' => $round->name ?? $round->round_id,
                        'parcel_type' => $quantity->parcelType ? $quantity->parcelType->name : 'Unknown',
                        'parcels' => $quantity->total_parcels,
                        'income' => $income,
                        'profit' => $profit,
                    ];
                });
        })->values()->toArray();

        // Prepare data for the income summary bar chart (all periods)
        $incomeByPeriod = $allPeriods->map(function ($period) use ($manifests, $rounds, $serviceProfile) {
            $periodManifests = $manifests->filter(function ($manifest) use ($period) {
                if (!$manifest->delivery_date) {
                    return false;
                }

                try {
                    $deliveryDate = new \DateTime($manifest->delivery_date);
                    $startDate = new \DateTime($period->start_date);
                    $endDate = new \DateTime($period->end_date);
                    return $deliveryDate >= $startDate && $deliveryDate <= $endDate;
                } catch (\Exception $e) {
                    \Log::error('Invalid date in income by period:', [
                        'manifest_id' => $manifest->id,
                        'delivery_date' => $manifest->delivery_date,
                        'period_id' => $period->id,
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                }
            });

            $income = $periodManifests->sum(function ($manifest) use ($rounds) {
                $round = $rounds->firstWhere('id', $manifest->round_id);
                if (!$round) {
                    return 0;
                }
                return $manifest->quantities->sum(function ($quantity) use ($round) {
                    $rate = $round->parcelTypes()
                        ->where('parcel_types.id', $quantity->parcel_type_id)
                        ->first()
                        ?->pivot
                        ->price ?? 0;
                    return $quantity->total_parcels * $rate;
                });
            });

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

        // Prepare data for the parcel type pie chart
        $pieChartData = [
            'labels' => array_column($totalPacketTypeData, 'name') ?: [],
            'data' => array_column($totalPacketTypeData, 'total') ?: [],
        ];

        return Inertia::render('Reports/Index', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriods' => $selectedPeriodIds,
            'rounds' => $rounds,
            'parcelTypes' => $parcelTypes,
            'reportData' => $reportData,
            'totalSummary' => $totalSummary,
            'incomeByPeriod' => $incomeByPeriod,
            'pieChartData' => $pieChartData,
            'deliveryPerformance' => $deliveryPerformance,
            'flash' => session('flash', []),
        ]);
    }
}
