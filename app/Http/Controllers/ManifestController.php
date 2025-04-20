<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\ManifestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManifestController extends Controller
{
    public function store(Request $request)
    {

        try {
            // Validate the request
            $validated = $request->validate([
                'manifest_date' => 'required|string',
                'parsed_manifest_date' => 'required|date_format:Y-m-d',
                'manifest' => 'required|string',
                'round' => 'required|string',
                'Manifested' => 'required|array',
                'Manifested.*.parcel_type' => 'required|string',
                'Manifested.*.manifested' => 'required|integer',
                'Re-manifested' => 'required|array',
                'Re-manifested.*.parcel_type' => 'required|string',
                'Re-manifested.*.manifested' => 'required|integer',
                'Carried forward' => 'required|array',
                'Carried forward.*.parcel_type' => 'required|string',
                'Carried forward.*.manifested' => 'required|integer',
            ]);

            // Check for duplicate manifest
            if (Manifest::where('manifest', $validated['manifest'])->exists()) {
                return response()->json([
                    'message' => 'Manifest already exists',
                    'manifest' => $validated['manifest'],
                ], 409);
            }

            // Start a transaction
            return DB::transaction(function () use ($validated) {
                // Create the manifest
                $manifest = Manifest::create([
                    'manifest_date' => $validated['manifest_date'],
                    'parsed_manifest_date' => $validated['parsed_manifest_date'],
                    'manifest' => $validated['manifest'],
                    'round' => $validated['round'],
                ]);

                // Create manifest items
                $items = [];
                foreach ($validated['Manifested'] as $index => $manifested) {
                    $items[] = [
                        'manifest_id' => $manifest->id,
                        'parcel_type' => $manifested['parcel_type'],
                        'manifested' => $manifested['manifested'],
                        're_manifested' => $validated['Re-manifested'][$index]['manifested'],
                        'carried_forward' => $validated['Carried forward'][$index]['manifested'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                ManifestItem::insert($items);

                return response()->json([
                    'message' => 'Manifest data stored successfully',
                    'manifest' => $manifest->load('items'),
                ], 201);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed: ' . json_encode($e->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to store manifest: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to store manifest',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
