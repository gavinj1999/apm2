<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('service_profiles', function (Blueprint $table) {
            $table->decimal('distance_home_to_work', 10, 2)->nullable()->after('distance_unit');
            $table->decimal('distance_work_to_start', 10, 2)->nullable()->after('distance_home_to_work');
            $table->decimal('distance_end_to_home', 10, 2)->nullable()->after('distance_work_to_start');
            $table->dropColumn(['distance_to_location', 'distance_from_location']);
        });
    }

    public function down()
    {
        Schema::table('service_profiles', function (Blueprint $table) {
            $table->dropColumn(['distance_home_to_work', 'distance_work_to_start', 'distance_end_to_home']);
            $table->decimal('distance_to_location', 10, 2)->nullable();
            $table->decimal('distance_from_location', 10, 2)->nullable();
        });
    }
};
