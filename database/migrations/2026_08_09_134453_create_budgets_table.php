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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['salary', 'planned']);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency_code', 10);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'closed', 'archived'])->default('active');
            $table->timestamps();
            
            $table->unique(['user_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};