<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('manifest_additional_metrics');
    }

    public function down(): void
    {
        Schema::create('manifest_additional_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->onDelete('cascade');
            $table->integer('next_day_manifested')->default(0);
            $table->integer('next_day_re_manifested')->default(0);
            $table->integer('next_day_carried_forward')->default(0);
            $table->integer('pod_signature_manifested')->default(0);
            $table->integer('pod_signature_re_manifested')->default(0);
            $table->integer('pod_signature_carried_forward')->default(0);
            $table->timestamps();
        });
    }
};
