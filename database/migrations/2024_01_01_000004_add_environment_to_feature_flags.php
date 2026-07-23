<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('feature-flags.table', 'feature_flags');

        Schema::table($table, function (Blueprint $t) use ($table): void {
            // Null = applies to every environment (back-compat with existing rows).
            $t->string('environment')->nullable()->after('scope_id');
            $t->dropUnique($table.'_key_scope_id_unique');
            $t->unique(['key', 'scope_id', 'environment']);
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.table', 'feature_flags');

        Schema::table($table, function (Blueprint $t): void {
            $t->dropUnique(['key', 'scope_id', 'environment']);
            $t->unique(['key', 'scope_id']);
            $t->dropColumn('environment');
        });
    }
};
