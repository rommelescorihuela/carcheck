<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('filled_on');
            $table->unsignedInteger('odometer_km');
            $table->decimal('liters', 8, 2);
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->boolean('full_tank')->default(true);
            $table->string('station', 150)->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->index(['vehicle_id', 'odometer_km']);
            $table->index(['vehicle_id', 'filled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_entries');
    }
};
