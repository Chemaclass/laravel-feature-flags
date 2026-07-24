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
            // {from, to, starts_at, ends_at} — time-interpolated rollout percentage.
            $t->json('ramp')->nullable()->after('rollout_percentage');
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.table', 'feature_flags');

        Schema::table($table, function (Blueprint $t): void {
            $t->dropColumn('ramp');
        });
    }
};
