<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('service_profile_id')->nullable()->after('id');
            $table->foreign('service_profile_id')->references('id')->on('service_profiles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['service_profile_id']);
            $table->dropColumn('service_profile_id');
        });
    }
};
