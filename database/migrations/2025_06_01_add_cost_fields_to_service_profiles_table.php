// database/migrations/YYYY_MM_DD_add_cost_fields_to_service_profiles_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_profiles', function (Blueprint $table) {
            $table->decimal('total_fuel_cost', 8, 2)->default(0)->after('loading_time_hours');
            $table->decimal('total_loading_cost', 8, 2)->default(0)->after('total_fuel_cost');
            $table->decimal('total_cost', 8, 2)->default(0)->after('total_loading_cost');
        });
    }

    public function down(): void
    {
        Schema::table('service_profiles', function (Blueprint $table) {
            $table->dropColumn(['total_fuel_cost', 'total_loading_cost', 'total_cost']);
        });
    }
};