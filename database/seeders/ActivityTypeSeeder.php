<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityType;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $activities = [
            ['name' => 'Left Home', 'alias' => 'Home', 'color' => '#10B981'], // Emerald-500
            ['name' => 'Arrive Depot', 'alias' => 'Depot In', 'color' => '#3B82F6'], // Blue-500
            ['name' => 'Start Loading', 'alias' => 'Load', 'color' => '#F59E0B'], // Amber-500
            ['name' => 'Leave Depot', 'alias' => 'Depot Out', 'color' => '#EF4444'], // Red-500
            ['name' => 'First Drop', 'alias' => 'First', 'color' => '#8B5CF6'], // Purple-500
            ['name' => 'Last Drop', 'alias' => 'Last', 'color' => '#EC4899'], // Pink-500
            ['name' => 'Arrive Home', 'alias' => 'Back', 'color' => '#22D3EE'], // Cyan-400
        ];

        foreach ($activities as $activity) {
            ActivityType::updateOrCreate(['name' => $activity['name']], $activity);
        }
    }
}