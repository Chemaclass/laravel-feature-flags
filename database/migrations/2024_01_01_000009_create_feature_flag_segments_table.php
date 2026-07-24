<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('feature-flags.segments.table', 'feature_flag_segments');

        Schema::create($table, function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('name')->unique();
            $t->json('conditions'); // list of {attr, op, value}, all AND together
            $t->string('description')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        $table = (string) config('feature-flags.segments.table', 'feature_flag_segments');
        Schema::dropIfExists($table);
    }
};
