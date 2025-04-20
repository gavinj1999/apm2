<?php

namespace App\Http\Controllers;

use App\Models\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PeriodController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $periods = Period::all();
        return Inertia::render('Periods/Index', ['periods' => $periods]);
    }

    public function create()
    {
        return Inertia::render('Periods/Index'); // Redirect to Index for modal
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        Period::create($validated);

        return redirect()->route('periods.index')->with('success', 'Period created successfully.');
    }

    public function edit(Period $period)
    {
        return Inertia::render('Periods/Index', ['period' => $period]); // Redirect to Index for modal
    }

    public function update(Request $request, Period $period)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $period->update($validated);

        return redirect()->route('periods.index')->with('success', 'Period updated successfully.');
    }

    public function destroy(Period $period)
    {
        $period->delete();
        return redirect()->route('periods.index')->with('success', 'Period deleted successfully.');
    }
}
