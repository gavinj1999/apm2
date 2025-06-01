<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_profiles')) {
            Schema::create('service_profiles', function (Blueprint $table) {
                $table->increments('id');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('round_id')->constrained()->onDelete('cascade');
                $table->decimal('fuel_cost_per_unit', 8, 2);
                $table->string('distance_unit', 10)->default('mile');
                $table->decimal('distance_home_to_work', 8, 2)->default(0);
                $table->decimal('distance_work_to_start', 8, 2)->default(0);
                $table->decimal('distance_end_to_home', 8, 2)->default(0);
                $table->decimal('loading_time_cost_per_hour', 8, 2)->default(0);
                $table->decimal('loading_time_hours', 8, 2)->default(0);
                $table->decimal('total_fuel_cost', 8, 2)->default(0);
                $table->decimal('total_loading_cost', 8, 2)->default(0);
                $table->decimal('total_cost', 8, 2)->default(0);
                $table->timestamps();
            });
        } else {
            Schema::table('service_profiles', function (Blueprint $table) {
                // Add user_id if missing
                if (!Schema::hasColumn('service_profiles', 'user_id')) {
                    $table->foreignId('user_id')->constrained()->onDelete('cascade')->after('id');
                }

                // Add round_id if missing
                if (!Schema::hasColumn('service_profiles', 'round_id')) {
                    $table->foreignId('round_id')->constrained()->onDelete('cascade')->after('user_id');
                }

                // Rename distance fields if they exist
                if (Schema::hasColumn('service_profiles', 'distance_to_location')) {
                    $table->renameColumn('distance_to_location', 'distance_home_to_work');
                }
                if (Schema::hasColumn('service_profiles', 'distance_from_location')) {
                    $table->renameColumn('distance_from_location', 'distance_end_to_home');
                }

                // Add new distance field if missing
                if (!Schema::hasColumn('service_profiles', 'distance_work_to_start')) {
                    $table->decimal('distance_work_to_start', 8, 2)->default(0)->after('distance_home_to_work');
                }

                // Add cost fields if missing
                if (!Schema::hasColumn('service_profiles', 'total_fuel_cost')) {
                    $table->decimal('total_fuel_cost', 8, 2)->default(0)->after('loading_time_hours');
                }
                if (!Schema::hasColumn('service_profiles', 'total_loading_cost')) {
                    $table->decimal('total_loading_cost', 8, 2)->default(0)->after('total_fuel_cost');
                }
                if (!Schema::hasColumn('service_profiles', 'total_cost')) {
                    $table->decimal('total_cost', 8, 2)->default(0)->after('total_loading_cost');
                }

                // Ensure other fields exist
                if (!Schema::hasColumn('service_profiles', 'fuel_cost_per_unit')) {
                    $table->decimal('fuel_cost_per_unit', 8, 2)->after('round_id');
                }
                if (!Schema::hasColumn('service_profiles', 'distance_unit')) {
                    $table->string('distance_unit', 10)->default('mile')->after('fuel_cost_per_unit');
                }
                if (!Schema::hasColumn('service_profiles', 'loading_time_cost_per_hour')) {
                    $table->decimal('loading_time_cost_per_hour', 8, 2)->default(0)->after('distance_end_to_home');
                }
                if (!Schema::hasColumn('service_profiles', 'loading_time_hours')) {
                    $table->decimal('loading_time_hours', 8, 2)->default(0)->after('loading_time_cost_per_hour');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_profiles')) {
            Schema::table('service_profiles', function (Blueprint $table) {
                // Drop foreign keys
                if (Schema::hasColumn('service_profiles', 'user_id')) {
                    $table->dropForeign(['user_id']);
                }
                if (Schema::hasColumn('service_profiles', 'round_id')) {
                    $table->dropForeign(['round_id']);
                }

                // Reverse renaming
                if (Schema::hasColumn('service_profiles', 'distance_home_to_work')) {
                    $table->renameColumn('distance_home_to_work', 'distance_to_location');
                }
                if (Schema::hasColumn('service_profiles', 'distance_end_to_home')) {
                    $table->renameColumn('distance_end_to_home', 'distance_from_location');
                }

                // Drop added columns
                $columns = [
                    'distance_work_to_start',
                    'total_fuel_cost',
                    'total_loading_cost',
                    'total_cost',
                    'user_id',
                    'round_id',
                    'fuel_cost_per_unit',
                    'distance_unit',
                    'loading_time_cost_per_hour',
                    'loading_time_hours',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('service_profiles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            // Drop table if it was newly created
            if (Schema::hasTable('service_profiles')) {
                Schema::dropIfExists('service_profiles');
            }
        }
    }
};
