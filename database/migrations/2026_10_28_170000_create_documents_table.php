<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('name', 150);
            $table->string('issuer', 150)->nullable();
            $table->string('reference', 100)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'expires_on']);
            $table->index(['vehicle_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
