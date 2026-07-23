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
            // [{"name":"blue","weight":50}, ...]; null = no variants.
            $t->json('variants')->nullable()->after('prerequisites');
            // {"blue": {...payload...}}; null = no payloads.
            $t->json('variant_payloads')->nullable()->after('variants');
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.table', 'feature_flags');

        Schema::table($table, function (Blueprint $t): void {
            $t->dropColumn(['variants', 'variant_payloads']);
        });
    }
};
