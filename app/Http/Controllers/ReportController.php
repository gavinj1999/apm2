<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\Manifest;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $periods = Period::all();
        $selectedPeriodId = $request->input('period_id');

        $query = Manifest::query()->with('items');

        if ($selectedPeriodId) {
            $period = Period::findOrFail($selectedPeriodId);
            $query->whereBetween('parsed_manifest_date', [$period->start_date, $period->end_date]);
        }

        $manifests = $query->get();

        // Aggregate data by parcel type
        $reportData = [];
        foreach ($manifests as $manifest) {
            foreach ($manifest->items as $item) {
                $parcelType = $item->parcel_type;
                if (!isset($reportData[$parcelType])) {
                    $reportData[$parcelType] = [
                        'manifested' => 0,
                        're_manifested' => 0,
                        'carried_forward' => 0,
                    ];
                }
                $reportData[$parcelType]['manifested'] += $item->manifested;
                $reportData[$parcelType]['re_manifested'] += $item->re_manifested;
                $reportData[$parcelType]['carried_forward'] += $item->carried_forward;
            }
        }

        return view('reports.index', compact('periods', 'selectedPeriodId', 'reportData'));
    }
}
