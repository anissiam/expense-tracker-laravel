<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Budget partners (per-budget sharing with editor/viewer roles).
     * A row with status=pending is an invitation; accepted rows grant access.
     */
    public function up(): void
    {
        Schema::create('budget_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            // Nullable: invitation for an email with no account yet.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->enum('role', ['editor', 'viewer'])->default('viewer');
            $table->enum('status', ['pending', 'accepted', 'declined', 'revoked'])->default('pending');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_members');
    }
};
