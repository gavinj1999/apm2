<?php
// app/Http/Controllers/HolidayController.php
namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'daily_rate' => 'required|numeric|min:0',
        ]);

        // Check for overlapping holidays
        $hasOverlap = Holiday::where(function ($query) use ($validated) {
            $query->where('start_date', '<=', $validated['end_date'])
                  ->where('end_date', '>=', $validated['start_date']);
        })->exists();

        if ($hasOverlap) {
            return back()->withErrors(['start_date' => 'Holiday period overlaps with an existing holiday.'])->withInput();
        }

        $holiday = Holiday::create($validated);

        return redirect()->route('dashboard')->with('flash', [
            'success' => 'Holiday created successfully.',
            'holiday' => [
                'id' => $holiday->id,
                'start_date' => $holiday->start_date->toDateString(),
                'end_date' => $holiday->end_date->toDateString(),
                'daily_rate' => (float) $holiday->daily_rate, // Cast to float
            ],
        ]);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'daily_rate' => 'required|numeric|min:0',
        ]);

        // Check for overlapping holidays, excluding the current holiday
        $hasOverlap = Holiday::where('id', '!=', $holiday->id)
            ->where(function ($query) use ($validated) {
                $query->where('start_date', '<=', $validated['end_date'])
                      ->where('end_date', '>=', $validated['start_date']);
            })->exists();

        if ($hasOverlap) {
            return back()->withErrors(['start_date' => 'Holiday period overlaps with an existing holiday.'])->withInput();
        }

        $holiday->update($validated);

        return redirect()->route('dashboard')->with('flash', [
            'success' => 'Holiday updated successfully.',
            'holiday' => [
                'id' => $holiday->id,
                'start_date' => $holiday->start_date->toDateString(),
                'end_date' => $holiday->end_date->toDateString(),
                'daily_rate' => (float) $holiday->daily_rate, // Cast to float
            ],
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return redirect()->route('dashboard')->with('flash', [
            'success' => 'Holiday deleted successfully.',
        ]);
    }
}