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

        Schema::create($table, function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('key')->index();
            $t->string('scope_id')->nullable()->index();
            $t->boolean('value')->default(false);
            $t->string('hint')->nullable();
            $t->boolean('is_dev')->default(false);
            $t->timestamp('enabled_from')->nullable();
            $t->timestamp('enabled_until')->nullable();
            $t->timestamps();

            $t->unique(['key', 'scope_id']);
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.table', 'feature_flags');
        Schema::dropIfExists($table);
    }
};
