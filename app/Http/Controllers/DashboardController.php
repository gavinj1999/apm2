<?php
namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Round;
use App\Models\ParcelType;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch all parcel types
        $parcelTypes = ParcelType::all();

        // Fetch rounds
        $rounds = Round::all();

        // Fetch individual manifests with their rounds and summaries
        $manifests = Manifest::with(['round', 'summaries.parcel_type'])
            ->where('user_id', auth()->id())
            ->orderBy('delivery_date', 'desc')
            ->get()
            ->map(function ($manifest) use ($parcelTypes) {
                // Aggregate quantities for each parcel type
                $quantities = $parcelTypes->map(function ($type) use ($manifest) {
                    $summary = $manifest->summaries->firstWhere('parcel_type_id', $type->id);
                    return [
                        'parcel_type_id' => $type->id,
                        'name' => $type->name,
                        'manifested' => $summary ? $summary->manifested : 0,
                        're_manifested' => $summary ? $summary->re_manifested : 0,
                        'carried_forward' => $summary ? $summary->carried_forward : 0,
                        'total' => $summary ? ($summary->manifested + $summary->re_manifested + $summary->carried_forward) : 0,
                    ];
                });

                // Calculate total monetary value for the manifest
                $totalValue = $manifest->summaries->reduce(function ($total, $summary) use ($manifest) {
                    $pricing = $manifest->round->pricings->firstWhere('parcel_type_id', $summary->parcel_type_id);
                    $price = $pricing ? $pricing->price : 0;
                    return $total + ($price * $summary->manifested);
                }, 0);

                return [
                    'id' => $manifest->id,
                    'delivery_date' => $manifest->delivery_date,
                    'round_id' => $manifest->round->round_id,
                    'quantities' => $quantities,
                    'total_value' => $totalValue,
                ];
            });

        // Calculate total earnings across all manifests
        $totalEarnings = Manifest::with(['round.pricings', 'summaries'])
            ->where('user_id', auth()->id())
            ->get()
            ->sum(function ($manifest) {
                return $manifest->summaries->reduce(function ($total, $summary) use ($manifest) {
                    $pricing = $manifest->round->pricings->firstWhere('parcel_type_id', $summary->parcel_type_id);
                    $price = $pricing ? $pricing->price : 0;
                    return $total + ($price * $summary->manifested);
                }, 0);
            });

        return Inertia::render('Dashboard', [
            'manifests' => $manifests, // Changed from groupedManifests
            'totalEarnings' => $totalEarnings,
            'rounds' => $rounds,
            'parcelTypes' => $parcelTypes,
        ]);
    }
}
