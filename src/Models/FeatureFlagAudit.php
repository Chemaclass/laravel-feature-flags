<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $key
 * @property ?string $scope_id
 * @property string $action
 * @property ?bool $old_value
 * @property ?bool $new_value
 * @property ?string $actor
 * @property ?Carbon $created_at
 */
class FeatureFlagAudit extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'scope_id',
        'action',
        'old_value',
        'new_value',
        'actor',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'old_value' => 'boolean',
        'new_value' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('feature-flags.audit.table', 'feature_flag_audits');
    }
}
