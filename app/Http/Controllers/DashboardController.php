<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Period;
use App\Models\Round;
use App\Models\ParcelType;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch all parcel types
        $parcelTypes = ParcelType::orderBy('sort_order')->get();

        // Fetch rounds for the authenticated user
        $rounds = Round::where('user_id', auth()->id())->get();

        // Fetch all periods
        $periods = Period::all();

        // Fetch individual manifests with their rounds and summaries
        $manifests = Manifest::with(['round', 'summaries.parcel_type'])
            ->where('user_id', auth()->id())
            ->orderBy('delivery_date', 'desc')
            ->orderBy('round_id', 'asc')
            ->get()
            ->map(function ($manifest) use ($parcelTypes) {
                // Debug: Log manifest and round data
                \Log::info('Manifest data:', [
                    'manifest_id' => $manifest->id,
                    'round_id' => $manifest->round_id,
                    'round_exists' => !is_null($manifest->round),
                ]);

                if (is_null($manifest->round)) {
                    // Skip this manifest if the round is missing
                    return null;
                }

                // Aggregate quantities for each parcel type
                $quantities = $parcelTypes->map(function ($type) use ($manifest) {
                    $summary = $manifest->summaries->firstWhere('parcel_type_id', $type->id);
                    // Debug: Log roundPricings data
                    \Log::info('RoundPricings data for manifest:', [
                        'manifest_id' => $manifest->id,
                        'round_id' => $manifest->round->id,
                        'roundPricings' => $manifest->round->roundPricings ? $manifest->round->roundPricings->toArray() : null,
                    ]);
                    $roundPricing = $manifest->round->roundPricings ? $manifest->round->roundPricings->firstWhere('parcel_type_id', $type->id) : null;
                    $price = $roundPricing ? $roundPricing->price : 0;
                    $manifested = $summary ? $summary->manifested : 0;
                    $value = $price * $manifested;

                    return [
                        'parcel_type_id' => $type->id,
                        'name' => $type->name,
                        'manifested' => $manifested,
                        're_manifested' => $summary ? $summary->re_manifested : 0,
                        'carried_forward' => $summary ? $summary->carried_forward : 0,
                        'total' => $summary ? ($summary->manifested + $summary->re_manifested + $summary->carried_forward) : 0,
                        'value' => $value,
                    ];
                });

                // Calculate total monetary value for the manifest
                $totalValue = $manifest->summaries->reduce(function ($total, $summary) use ($manifest) {
                    $roundPricing = $manifest->round->roundPricings ? $manifest->round->roundPricings->firstWhere('parcel_type_id', $summary->parcel_type_id) : null;
                    $price = $roundPricing ? $roundPricing->price : 0;
                    return $total + ($price * $summary->manifested);
                }, 0);

                return [
                    'id' => $manifest->id,
                    'delivery_date' => $manifest->delivery_date,
                    'round_id' => $manifest->round->round_id,
                    'quantities' => $quantities,
                    'total_value' => $totalValue,
                ];
            })
            ->filter(); // Remove null values (manifests with missing rounds)

        // Group manifests by period, then by date, then by round
        $groupedManifests = $periods->map(function ($period) use ($manifests) {
            $periodManifests = $manifests->filter(function ($manifest) use ($period) {
                $deliveryDate = \Carbon\Carbon::parse($manifest['delivery_date']);
                return $deliveryDate->between($period->start_date, $period->end_date);
            });

            // Skip this period if there are no manifests
            if ($periodManifests->isEmpty()) {
                return null;
            }

            // Group by date
            $byDate = $periodManifests->groupBy('delivery_date')->map(function ($dateManifests) {
                // Group by round within each date
                $byRound = $dateManifests->groupBy('round_id')->map(function ($roundManifests) {
                    return $roundManifests->first();
                })->values();

                return [
                    'date' => $dateManifests->first()['delivery_date'],
                    'manifests' => $byRound,
                ];
            })->values();

            return [
                'period_name' => $period->name,
                'dates' => $byDate,
            ];
        })->filter()->values();

        // Calculate total earnings across all manifests
        $totalEarnings = Manifest::with(['round.roundPricings', 'summaries'])
            ->where('user_id', auth()->id())
            ->get()
            ->sum(function ($manifest) {
                // Debug: Log manifest and round data for total earnings
                \Log::info('Total earnings manifest data:', [
                    'manifest_id' => $manifest->id,
                    'round_id' => $manifest->round_id,
                    'round_exists' => !is_null($manifest->round),
                ]);

                if (is_null($manifest->round)) {
                    return 0; // Skip this manifest if the round is missing
                }

                return $manifest->summaries->reduce(function ($total, $summary) use ($manifest) {
                    $roundPricing = $manifest->round->roundPricings ? $manifest->round->roundPricings->firstWhere('parcel_type_id', $summary->parcel_type_id) : null;
                    $price = $roundPricing ? $roundPricing->price : 0;
                    return $total + ($price * $summary->manifested);
                }, 0);
            });

        return Inertia::render('Dashboard', [
            'groupedManifests' => $groupedManifests,
            'totalEarnings' => $totalEarnings,
            'rounds' => $rounds,
            'parcelTypes' => $parcelTypes,
        ]);
    }
}
