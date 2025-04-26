<?php

namespace App\Http\Controllers;

use App\Models\NMWCalculator;
use App\Http\Requests\StoreNMWCalculatorRequest;
use App\Http\Requests\UpdateNMWCalculatorRequest;

class NMWCalculatorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('Calculators/Index', [
            'nmw_calculator' => NMWCalculator::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNMWCalculatorRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(NMWCalculator $nMWCalculator)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(NMWCalculator $nMWCalculator)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNMWCalculatorRequest $request, NMWCalculator $nMWCalculator)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(NMWCalculator $nMWCalculator)
    {
        //
    }
}
