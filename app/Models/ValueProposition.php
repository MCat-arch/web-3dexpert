<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Replaces the list1/list2 rows of mengapa_section.
 *
 * Legacy stored two unrelated lists in one table, skipped row 0 by array index,
 * and "deleted" an item by nulling its column -- which left permanent all-null
 * orphan rows. One row per item with an explicit group fixes all three.
 */
class ValueProposition extends Model
{
    use HasFactory, HasTranslations;

    /** Legacy list1 */
    public const GROUP_BENEFIT = 'benefit';

    /** Legacy list2 */
    public const GROUP_PROCESS = 'process';

    /** Landing-page deliverables grid seeded from the legacy hardcoded tiles. */
    public const GROUP_DELIVERABLE = 'deliverable';

    protected array $translatable = ['label'];

    protected $fillable = [
        'group', 'label', 'icon_path', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function groups(): array
    {
        return [self::GROUP_BENEFIT, self::GROUP_PROCESS, self::GROUP_DELIVERABLE];
    }
}
