<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->unsignedInteger('total_rooms_rented')->default(1);

            $table->decimal('monthly_rent', 12, 2);

            $table->decimal('security_deposit', 12, 2)
                ->default(0);

            $table->date('start_date');

            $table->date('end_date')->nullable();

            $table->enum('status', [
                'pending',
                'active',
                'ended',
                'cancelled',
            ])->default('active');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Useful indexes
            $table->index(['property_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};