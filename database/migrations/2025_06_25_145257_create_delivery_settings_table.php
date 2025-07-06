<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->float('value');
            $table->timestamps();
        });

        DB::table('delivery_settings')->insert([
            ['key' => 'home_to_depot_distance', 'value' => 10.0, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'first_drop_distance', 'value' => 10.0, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'last_drop_distance', 'value' => 10.0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('delivery_settings');
    }
};