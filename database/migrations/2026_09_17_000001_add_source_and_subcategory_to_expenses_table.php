<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1+2: direct expense entry with subcategory + voice source tracking.
     * Automatic budget deduction is derived from allocations + expenses,
     * so no allocated column is touched here — this only adds attribution.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'subcategory_id')) {
                $table->foreignId('subcategory_id')->nullable()->after('category_id')
                    ->constrained('categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'source')) {
                $table->string('source', 20)->default('manual')->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('expenses', 'subcategory_id')) {
                $table->dropConstrainedForeignId('subcategory_id');
            }
        });
    }
};
