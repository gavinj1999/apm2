<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Round;
use App\Models\ParcelType;
use App\Models\ManifestSummary;
use App\Models\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ManifestController extends Controller
{
    /**
     * Display a listing of manifests (for /manifests route, not dashboard).
     */
    public function index()
    {
        $manifests = Manifest::with(['round', 'quantities'])
            ->whereIn('round_id', Round::where('user_id', auth()->id())->pluck('id'))
            ->orderBy('delivery_date', 'desc')
            ->get();

        $parcelTypes = ParcelType::all();
        $rounds = Round::where('user_id', auth()->id())->get();

        return Inertia::render('Manifests/Index', [
            'manifests' => $manifests->toArray(),
            'parcelTypes' => $parcelTypes->toArray(),
            'rounds' => $rounds->toArray(),
            'flash' => session('flash', []),
        ]);
    }

    /**
     * Show the form for creating a new manifest.
     */
    public function create()
    {
        $parcelTypes = ParcelType::all();
        $rounds = Round::where('user_id', auth()->id())->get();

        return Inertia::render('Manifests/Create', [
            'parcelTypes' => $parcelTypes->toArray(),
            'rounds' => $rounds->toArray(),
            'flash' => session('flash', []),
        ]);
    }

    /**
     * Store a newly created manifest in storage.
     */
    public function store(Request $request)
    {
        $parcelTypes = Cache::remember('parcel_types_ids', 60 * 60 * 24, function () {
            return ParcelType::pluck('id')->toArray();
        });

        try {
            $validated = $request->validate([
                'delivery_date' => [
                    'required',
                    'date',
                    function ($attribute, $value, $fail) use ($request) {
                        // Check if a manifest already exists for this delivery_date and round_id
                        $existingManifest = Manifest::where('delivery_date', $value)
                            ->where('round_id', $request->input('round_id'))
                            ->where('user_id', auth()->id())
                            ->first();

                        if ($existingManifest) {
                            $fail("A manifest for this delivery date and round already exists.");
                        }
                    },
                ],
                'status' => 'required|in:pending,in-progress,completed',
                'round_id' => ['required', Rule::exists('rounds', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                })],
                'quantities' => 'required|array',
                'quantities.*.parcel_type_id' => ['required', 'integer', Rule::in($parcelTypes)],
                'quantities.*.manifested' => 'nullable|integer|min:0',
                'quantities.*.re_manifested' => 'nullable|integer|min:0',
                'quantities.*.carried_forward' => 'nullable|integer|min:0',
            ]);

            Log::info('Manifest store request', [
                'input' => $request->all(),
                'parcel_types' => $parcelTypes,
            ]);

            DB::beginTransaction();

            $manifestNumber = 'MAN-' . now()->format('Ymd') . '-' . Str::random(8);

            $manifest = Manifest::create([
                'manifest_number' => $manifestNumber,
                'delivery_date' => $validated['delivery_date'],
                'status' => $validated['status'],
                'round_id' => $validated['round_id'],
                'user_id' => auth()->id(),
            ]);

            $quantityData = array_map(function ($quantity) use ($manifest) {
                return [
                    'manifest_id' => $manifest->id,
                    'parcel_type_id' => $quantity['parcel_type_id'],
                    'manifested' => $quantity['manifested'] ?? 0,
                    're_manifested' => $quantity['re_manifested'] ?? 0,
                    'carried_forward' => $quantity['carried_forward'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $validated['quantities']);

            ManifestSummary::insert($quantityData);

            DB::commit();

            Log::info('Manifest created', ['manifest_id' => $manifest->id, 'user_id' => auth()->id()]);

            return redirect()->route('dashboard')->with('flash', [
                'success' => 'Manifest created successfully!'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for manifest creation', [
                'errors' => $e->errors(),
                'input' => $request->all(),
                'parcel_types' => $parcelTypes,
            ]);
            return back()->withErrors($e->errors())->with('flash', [
                'error' => 'Failed to create manifest due to validation errors.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create manifest', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
                'parcel_types' => $parcelTypes,
            ]);
            return back()->with('flash', [
                'error' => 'Failed to create manifest: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display the specified manifest.
     */
    public function show($id)
    {
        try {
            if (!auth()->check()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $manifest = Manifest::with(['round', 'quantities'])->find($id);
            if (!$manifest) {
                return response()->json(['error' => 'Manifest not found'], 404);
            }

            if (!$manifest->round) {
                return response()->json(['error' => 'Associated round not found'], 404);
            }

            if ($manifest->round->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $quantities = [];
            try {
                if ($manifest->quantities && !$manifest->quantities->isEmpty()) {
                    $quantities = $manifest->quantities->map(function ($quantity) {
                        return [
                            'parcel_type_id' => (int) $quantity->parcel_type_id,
                            'manifested' => (int) ($quantity->manifested ?? 0),
                            're_manifested' => (int) ($quantity->re_manifested ?? 0),
                            'carried_forward' => (int) ($quantity->carried_forward ?? 0),
                        ];
                    })->toArray();
                }
            } catch (\Exception $e) {
                $quantities = [];
            }

            return response()->json([
                'id' => $manifest->id,
                'delivery_date' => $manifest->delivery_date,
                'status' => $manifest->status,
                'round_id' => $manifest->round_id,
                'quantities' => $quantities,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error while fetching manifest'], 500);
        }
    }

    /**
     * Update the specified manifest in storage.
     */
    public function update(Request $request, $id)
    {
        $manifest = Manifest::findOrFail($id);

        if ($manifest->round->user_id !== auth()->id()) {
            return redirect()->route('dashboard')->with('flash', [
                'error' => 'Unauthorized to update this manifest.'
            ]);
        }

        $parcelTypes = Cache::remember('parcel_types_ids', 60 * 60 * 24, function () {
            return ParcelType::pluck('id')->toArray();
        });

        try {
            $validated = $request->validate([
                'delivery_date' => [
                    'required',
                    'date',
                    function ($attribute, $value, $fail) use ($request, $id) {
                        // Check if another manifest exists for this delivery_date and round_id
                        $existingManifest = Manifest::where('delivery_date', $value)
                            ->where('round_id', $request->input('round_id'))
                            ->where('user_id', auth()->id())
                            ->where('id', '!=', $id) // Exclude the current manifest being updated
                            ->first();

                        if ($existingManifest) {
                            $fail("A manifest for this delivery date and round already exists.");
                        }
                    },
                ],
                'status' => 'required|in:pending,in-progress,completed',
                'round_id' => ['required', Rule::exists('rounds', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                })],
                'quantities' => 'required|array',
                'quantities.*.parcel_type_id' => ['required', 'integer', Rule::in($parcelTypes)],
                'quantities.*.manifested' => 'nullable|integer|min:0',
                'quantities.*.re_manifested' => 'nullable|integer|min:0',
                'quantities.*.carried_forward' => 'nullable|integer|min:0',
            ]);

            Log::info('Manifest update request', [
                'input' => $request->all(),
                'parcel_types' => $parcelTypes,
            ]);

            DB::beginTransaction();

            $manifest->update([
                'delivery_date' => $validated['delivery_date'],
                'status' => $validated['status'],
                'round_id' => $validated['round_id'],
            ]);

            $manifest->quantities()->delete();

            foreach ($validated['quantities'] as $quantity) {
                ManifestSummary::create([
                    'manifest_id' => $manifest->id,
                    'parcel_type_id' => $quantity['parcel_type_id'],
                    'manifested' => $quantity['manifested'] ?? 0,
                    're_manifested' => $quantity['re_manifested'] ?? 0,
                    'carried_forward' => $quantity['carried_forward'] ?? 0,
                ]);
            }

            DB::commit();

            Log::info('Manifest updated', ['manifest_id' => $manifest->id, 'user_id' => auth()->id()]);

            return redirect()->route('dashboard')->with('flash', [
                'success' => 'Manifest updated successfully!'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for manifest update', [
                'errors' => $e->errors(),
                'input' => $request->all(),
                'parcel_types' => $parcelTypes,
            ]);
            return back()->withErrors($e->errors())->with('flash', [
                'error' => 'Failed to update manifest due to validation errors.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update manifest', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
                'parcel_types' => $parcelTypes,
            ]);
            return back()->with('flash', [
                'error' => 'Failed to update manifest: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Remove the specified manifest from storage.
     */
    public function destroy($id)
    {
        try {
            $manifest = Manifest::findOrFail($id);

            if ($manifest->round->user_id !== auth()->id()) {
                return redirect()->route('dashboard')->with('flash', [
                    'error' => 'Unauthorized to delete this manifest.'
                ]);
            }

            $manifest->quantities()->delete();
            $manifest->delete();

            Log::info('Manifest deleted', ['manifest_id' => $id, 'user_id' => auth()->id()]);

            return redirect()->route('dashboard')->with('flash', [
                'success' => 'Manifest deleted successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete manifest', [
                'error' => $e->getMessage(),
                'manifest_id' => $id,
            ]);
            return back()->with('flash', [
                'error' => 'Failed to delete manifest: ' . $e->getMessage()
            ]);
        }
    }
}
