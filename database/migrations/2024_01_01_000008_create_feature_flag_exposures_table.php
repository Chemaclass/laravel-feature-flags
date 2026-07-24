<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('feature-flags.analytics.table', 'feature_flag_exposures');

        Schema::create($table, function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('key')->index();
            $t->string('variant')->default(''); // '' = no variant (plain evaluation)
            $t->boolean('enabled');
            $t->unsignedBigInteger('count')->default(0);
            $t->timestamp('last_seen_at')->nullable();

            $t->unique(['key', 'variant', 'enabled']);
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.analytics.table', 'feature_flag_exposures');
        Schema::dropIfExists($table);
    }
};
