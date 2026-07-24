<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A reusable, named set of targeting conditions that flags can reference.
 *
 * @property string $id
 * @property string $name
 * @property array<int, array<string, mixed>> $conditions
 * @property ?string $description
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class FeatureFlagSegment extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'conditions',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'conditions' => 'array',
    ];

    public function getTable(): string
    {
        return (string) config('feature-flags.segments.table', 'feature_flag_segments');
    }
}
