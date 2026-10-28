<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            // Huella de la condición concreta: evita duplicados y reavisos al regenerar.
            $table->string('fingerprint', 160)->unique();
            $table->string('title', 160);
            $table->string('body', 500);
            $table->date('due_on')->nullable();
            $table->unsignedInteger('due_km')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
