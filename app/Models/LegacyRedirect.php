<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyRedirect extends Model
{
    protected $fillable = ['old_path', 'new_path', 'status_code'];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * Counting hits is how you find out, after cutover, which old URLs are
     * still being requested -- and therefore which ones you forgot to map.
     * Deliberately a bare atomic increment: no model events, no timestamps.
     */
    public function recordHit(): void
    {
        $this->newQuery()->whereKey($this->getKey())->update([
            'hit_count' => $this->getConnection()->raw('hit_count + 1'),
            'last_hit_at' => now(),
        ]);
    }
}
