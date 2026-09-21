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
        Schema::create('budget_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('remaining_amount', 15, 2);
            $table->enum('action', ['carry_forward', 'to_savings', 'split', 'ignore']);
            $table->decimal('to_next_budget_amount', 15, 2)->default(0);
            $table->decimal('to_savings_amount', 15, 2)->default(0);
            $table->foreignId('savings_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_closings');
    }
};