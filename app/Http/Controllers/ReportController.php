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

        $rounds = Round::where('user_id', auth()->id())->get();

        // Fetch manifests for the user with their quantities and parcel types
        $manifests = Manifest::where('user_id', auth()->id())
            ->with(['round', 'quantities.parcelType'])
            ->get();

        // Filter manifests by the selected periods' date ranges
        $filteredManifests = $manifests->filter(function ($manifest) use ($selectedPeriods) {
            return $selectedPeriods->contains(function ($period) use ($manifest) {
                // Convert delivery_date to DateTime for comparison
                $deliveryDate = new \DateTime($manifest->delivery_date);
                $startDate = new \DateTime($period->start_date);
                $endDate = new \DateTime($period->end_date);
                return $deliveryDate >= $startDate && $deliveryDate <= $endDate;
            });
        });

        // Group data by round and period
        $reportData = $rounds->flatMap(function ($round) use ($filteredManifests, $selectedPeriods) {
            return $selectedPeriods->map(function ($period) use ($round, $filteredManifests) {
                $roundManifests = $filteredManifests->filter(function ($manifest) use ($round, $period) {
                    // Convert dates to DateTime for comparison
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
                            'name' => $parcelType->name,
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

        return Inertia::render('Reports/Index', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriods' => $selectedPeriodIds,
            'reportData' => $reportData,
            'flash' => session('flash', []),
        ]);
    }
}
