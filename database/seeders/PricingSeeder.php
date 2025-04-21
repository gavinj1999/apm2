<?php

namespace Database\Seeders;
use App\Models\Round;
use App\Models\ParcelType;
use App\Models\RoundPricing;
use Illuminate\Database\Seeder;
class PricingSeeder extends Seeder
{
    public function run()
    {
        // Rounds
        $round1 = Round::create(['round_id' => '596434']);
        $round2 = Round::create(['round_id' => '598208']);
        // Parcel Types
        $types = ['Postable', 'Small Packet', 'Packet', 'Parcels', 'Hanging Garment', 'Heavy'];
        foreach ($types as $type) {
            ParcelType::create(['name' => $type]);
        }
        // Pricing for 596434
        RoundPricing::create(['round_id' => $round1->id, 'parcel_type_id' => ParcelType::where('name', 'Postable')->first()->id, 'price' => 0.56]);
        RoundPricing::create(['round_id' => $round1->id, 'parcel_type_id' => ParcelType::where('name', 'Small Packet')->first()->id, 'price' => 0.65]);
        RoundPricing::create(['round_id' => $round1->id, 'parcel_type_id' => ParcelType::where('name', 'Packet')->first()->id, 'price' => 0.71]);
        RoundPricing::create(['round_id' => $round1->id, 'parcel_type_id' => ParcelType::where('name', 'Parcels')->first()->id, 'price' => 0.94]);
        RoundPricing::create(['round_id' => $round1->id, 'parcel_type_id' => ParcelType::where('name', 'Heavy')->first()->id, 'price' => 1.00]);
        RoundPricing::create(['round_id' => $round1->id, 'parcel_type_id' => ParcelType::where('name', 'Hanging Garment')->first()->id, 'price' => 0.94]);
        // Pricing for 598208
        RoundPricing::create(['round_id' => $round2->id, 'parcel_type_id' => ParcelType::where('name', 'Postable')->first()->id, 'price' => 0.50]);
        RoundPricing::create(['round_id' => $round2->id, 'parcel_type_id' => ParcelType::where('name', 'Small Packet')->first()->id, 'price' => 0.58]);
        RoundPricing::create(['round_id' => $round2->id, 'parcel_type_id' => ParcelType::where('name', 'Packet')->first()->id, 'price' => 0.63]);
        RoundPricing::create(['round_id' => $round2->id, 'parcel_type_id' => ParcelType::where('name', 'Parcels')->first()->id, 'price' => 0.84]);
        RoundPricing::create(['round_id' => $round2->id, 'parcel_type_id' => ParcelType::where('name', 'Heavy')->first()->id, 'price' => 1.00]);
        RoundPricing::create(['round_id' => $round2->id, 'parcel_type_id' => ParcelType::where('name', 'Hanging Garment')->first()->id, 'price' => 0.84]);
    }
}
