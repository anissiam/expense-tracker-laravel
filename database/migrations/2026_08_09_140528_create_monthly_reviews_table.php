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
        Schema::create('monthly_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('total_budget', 15, 2);
            $table->decimal('total_spent', 15, 2);
            $table->decimal('total_saved', 15, 2);
            $table->foreignId('top_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('most_expensive_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->decimal('average_daily_spending', 15, 2);
            $table->jsonb('review_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_reviews');
    }
};