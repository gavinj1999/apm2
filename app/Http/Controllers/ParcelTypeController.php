<?php

namespace App\Http\Controllers;

use App\Models\ParcelType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParcelTypeController extends Controller
{
    public function index()
    {
        $parcelTypes = ParcelType::all();

        return Inertia::render('ParcelTypes/Index', [
            'parcelTypes' => $parcelTypes,
            'flash' => session('flash', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        ParcelType::create($validated);

        return redirect()->route('parcel-types.index')->with('success', 'Parcel type created successfully!');
    }

    public function update(Request $request, ParcelType $parcelType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $parcelType->update($validated);

        return redirect()->route('parcel-types.index')->with('success', 'Parcel type updated successfully!');
    }

    public function destroy(ParcelType $parcelType)
    {
        $parcelType->delete();

        return redirect()->route('parcel-types.index')->with('success', 'Parcel type deleted successfully!');
    }

    public function updateSortOrder(Request $request)
    {
        $order = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:parcel_types,id',
            'order.*.sort_order' => 'required|integer|min:1',
        ])['order'];

        foreach ($order as $item) {
            ParcelType::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }
}
