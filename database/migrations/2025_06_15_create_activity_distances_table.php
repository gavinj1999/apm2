<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('distances', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('segment'); // e.g., 'home_to_depot', 'leave_depot_to_first_drop', 'last_drop_to_home'
            $table->float('distance'); // Distance in kilometers
            $table->unsignedBigInteger('activity_from_id')->nullable();
            $table->unsignedBigInteger('activity_to_id')->nullable();
            $table->timestamps();

            $table->foreign('activity_from_id')->references('id')->on('activities')->onDelete('set null');
            $table->foreign('activity_to_id')->references('id')->on('activities')->onDelete('set null');
            $table->unique(['date', 'segment']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('distances');
    }
};