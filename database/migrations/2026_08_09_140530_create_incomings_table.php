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
        Schema::create('incomings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->date('expected_date');
            $table->foreignId('account_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('category')->nullable();
            $table->enum('recurrence', ['once', 'monthly', 'yearly'])->default('once');
            $table->enum('status', ['pending', 'received'])->default('pending');
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomings');
    }
};