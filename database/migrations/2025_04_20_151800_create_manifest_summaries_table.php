<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateManifestSummariesTable extends Migration
{
    public function up()
    {
        Schema::create('manifest_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->onDelete('cascade');
            $table->foreignId('parcel_type_id')->constrained()->onDelete('cascade');
            $table->integer('manifested')->default(0);
            $table->integer('re_manifested')->default(0);
            $table->integer('carried_forward')->default(0);
            $table->timestamps();
        });

        // Additional table for Next Day and POD-Signature counts (not tied to parcel types)
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
    public function down()
    {
        Schema::dropIfExists('manifest_summaries');
        Schema::dropIfExists('manifest_additional_metrics');
    }
}
