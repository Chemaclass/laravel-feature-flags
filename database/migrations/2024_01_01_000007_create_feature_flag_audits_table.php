<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('feature-flags.audit.table', 'feature_flag_audits');

        Schema::create($table, function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('key')->index();
            $t->string('scope_id')->nullable();
            $t->string('action');
            $t->boolean('old_value')->nullable();
            $t->boolean('new_value')->nullable();
            $t->string('actor')->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.audit.table', 'feature_flag_audits');
        Schema::dropIfExists($table);
    }
};
