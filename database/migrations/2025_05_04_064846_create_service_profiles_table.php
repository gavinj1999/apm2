<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('service_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('round_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('fuel_cost_per_unit', 8, 2); // £ per mile/km
            $table->string('distance_unit')->default('mile'); // mile or km
            $table->decimal('loading_time_cost_per_hour', 8, 2); // £ per hour
            $table->decimal('distance_to_location', 8, 2); // Distance to service location
            $table->decimal('distance_from_location', 8, 2); // Distance from service location
            $table->decimal('loading_time_hours', 8, 2); // Hours spent loading
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_profiles');
    }
}
