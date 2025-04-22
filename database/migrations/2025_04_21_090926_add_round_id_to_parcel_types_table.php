<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_types', function (Blueprint $table) {
            $table->foreignId('round_id')->nullable()->constrained()->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_types', function (Blueprint $table) {
            $table->dropForeign(['round_id']);
            $table->dropColumn('round_id');
        });
    }
};
