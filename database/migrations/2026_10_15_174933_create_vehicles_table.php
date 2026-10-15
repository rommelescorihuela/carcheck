<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('make', 100);
            $table->string('model', 100);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('plate', 16)->nullable()->index();
            $table->string('vin', 32)->nullable()->unique();
            $table->string('type', 20)->default('car');
            $table->string('fuel_type', 20)->default('gasoline');
            $table->unsignedInteger('odometer_km')->default(0);
            $table->string('color', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
