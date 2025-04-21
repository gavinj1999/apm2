<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class DropManifestItemsTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('manifest_items');
    }
    public function down()
    {
        Schema::create('manifest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->onDelete('cascade');
            $table->string('tracking_number')->unique();
            $table->string('recipient_name');
            $table->string('delivery_address');
            $table->string('status')->default('pending');
            $table->foreignId('parcel_type_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }
}
