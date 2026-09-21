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
        Schema::create('saving_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['add', 'withdraw', 'transfer', 'interest']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->foreignId('related_budget_id')->nullable()->constrained('budgets')->nullOnDelete();
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_transactions');
    }
};