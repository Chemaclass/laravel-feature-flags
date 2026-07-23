<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $key
 * @property bool $value
 * @property ?int $rollout_percentage
 * @property ?array<int, array<string, mixed>> $rules
 * @property ?array<int, string> $prerequisites
 * @property ?string $scope_id
 * @property ?string $environment
 * @property ?string $hint
 * @property bool $is_dev
 * @property ?Carbon $enabled_from
 * @property ?Carbon $enabled_until
 */
class FeatureFlag extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'scope_id',
        'environment',
        'value',
        'rollout_percentage',
        'rules',
        'prerequisites',
        'hint',
        'is_dev',
        'enabled_from',
        'enabled_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'boolean',
        'rollout_percentage' => 'integer',
        'rules' => 'array',
        'prerequisites' => 'array',
        'is_dev' => 'boolean',
        'enabled_from' => 'datetime',
        'enabled_until' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('feature-flags.table', 'feature_flags');
    }
}
