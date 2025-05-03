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

        $manifests = Manifest::where('user_id', auth()->id())
            ->with(['round', 'quantities.parcelType'])
            ->get();

        $filteredManifests = $manifests->filter(function ($manifest) use ($selectedPeriods) {
            foreach ($selectedPeriods as $period) {
                if (
                    $manifest->delivery_date >= $period->start_date &&
                    $manifest->delivery_date <= $period->end_date
                ) {
                    return true;
                }
            }
            return false;
        });

        $reportData = $rounds->mapWithKeys(function ($round) use ($filteredManifests) {
            $roundManifests = $filteredManifests->where('round_id', $round->id);

            $totalParcels = $roundManifests->sum(function ($manifest) {
                return $manifest->quantities->sum('total_parcels');
            });

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

            return [$round->id => [
                'name' => $round->name ?? $round->round_id,
                'total_parcels' => $totalParcels,
                'total_income' => $totalIncome,
                'packet_types' => $packetTypeData,
            ]];
        })->filter(fn($data) => $data['total_parcels'] > 0)->toArray();

        return Inertia::render('Reports/Index', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriods' => $selectedPeriodIds,
            'reportData' => $reportData,
            'flash' => session('flash', []),
        ]);
    }
}
