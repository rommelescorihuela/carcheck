<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
            $table->date('performed_on');
            $table->unsignedInteger('odometer_km');
            $table->string('provider', 150)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('next_due_km')->nullable();
            $table->date('next_due_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
