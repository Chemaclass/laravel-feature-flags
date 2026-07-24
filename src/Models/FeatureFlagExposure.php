<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Aggregated exposure counter: how often a flag resolved to a given result /
 * variant. One row per (key, variant, enabled).
 *
 * @property string $id
 * @property string $key
 * @property string $variant
 * @property bool $enabled
 * @property int $count
 * @property ?Carbon $last_seen_at
 */
class FeatureFlagExposure extends Model
{
    use HasUlids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'variant',
        'enabled',
        'count',
        'last_seen_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'enabled' => 'boolean',
        'count' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('feature-flags.analytics.table', 'feature_flag_exposures');
    }
}
