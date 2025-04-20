<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Period;
use Carbon\Carbon;

class PeriodSeeder extends Seeder
{
    public function run()
    {
        $periods = [
            ['name' => 'Period 12', 'start_date' => '2025-02-01', 'end_date' => '2025-02-28'],
            ['name' => 'Period 1', 'start_date' => '2025-03-01', 'end_date' => '2025-03-28'],
            ['name' => 'Period 2', 'start_date' => '2025-03-29', 'end_date' => '2025-05-02'],
            ['name' => 'Period 3', 'start_date' => '2025-05-03', 'end_date' => '2025-05-30'],
            ['name' => 'Period 4', 'start_date' => '2025-05-31', 'end_date' => '2025-06-27'],
            ['name' => 'Period 5', 'start_date' => '2025-06-28', 'end_date' => '2025-08-01'],
            ['name' => 'Period 6', 'start_date' => '2025-08-02', 'end_date' => '2025-08-29'],
            ['name' => 'Period 7', 'start_date' => '2025-08-30', 'end_date' => '2025-09-26'],
        ];

        foreach ($periods as $period) {
            Period::create($period);
        }
    }
}
