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

        Schema::table($table, function (Blueprint $t): void {
            // 0..100. Null = no rollout gate (pure boolean behaviour).
            $t->unsignedTinyInteger('rollout_percentage')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.table', 'feature_flags');

        Schema::table($table, function (Blueprint $t): void {
            $t->dropColumn('rollout_percentage');
        });
    }
};
