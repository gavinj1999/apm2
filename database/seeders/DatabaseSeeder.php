<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Round;
use App\Models\ParcelType;
use App\Models\RoundPricing;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

            // Clear existing data to avoid duplicates
            // Round::truncate();
            // ParcelType::truncate();
            // RoundPricing::truncate();

            // Create Rounds
            $round1 = Round::create(['round_id' => '596434', 'name' => 'Round 596434']);
            $round2 = Round::create(['round_id' => '598208', 'name' => 'Round 598208']);

            // Create Parcel Types (including Next Day and POD-Signature)
            $parcelTypes = [
                'Postable' => ['596434' => 0.56, '598208' => 0.50],
                'Small Packet' => ['596434' => 0.65, '598208' => 0.58],
                'Packet' => ['596434' => 0.71, '598208' => 0.63],
                'Parcels' => ['596434' => 0.94, '598208' => 0.84],
                'Heavy' => ['596434' => 1.00, '598208' => 1.00],
                'Hanging Garment' => ['596434' => 0.94, '598208' => 0.84],
                'Next Day' => ['596434' => 0.94, '598208' => 0.84], // Assuming same pricing as Parcels
                'POD-Signature' => ['596434' => 0.94, '598208' => 0.84], // Assuming same pricing as Parcels
            ];

            foreach ($parcelTypes as $typeName => $prices) {
                $parcelType = ParcelType::create(['name' => $typeName]);

                // Create RoundPricing for each round
                RoundPricing::create([
                    'round_id' => $round1->id,
                    'parcel_type_id' => $parcelType->id,
                    'price' => $prices['596434']
                ]);
                RoundPricing::create([
                    'round_id' => $round2->id,
                    'parcel_type_id' => $parcelType->id,
                    'price' => $prices['598208']
                ]);
            }
        }


}
