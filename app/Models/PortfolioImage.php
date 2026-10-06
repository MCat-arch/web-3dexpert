<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioImage extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['alt_text'];

    protected $fillable = [
        'portfolio_id', 'image_path', 'external_url', 'alt_text',
        'is_cover', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * Legacy property_images.image_url held a mix of local filenames and
     * absolute Unsplash URLs, so both cases have to resolve here.
     */
    public function getUrlAttribute(): ?string
    {
        if (filled($this->external_url)) {
            return $this->external_url;
        }

        if (filled($this->image_path)) {
            return route('media.show', ['path' => $this->image_path]);
        }

        return null;
    }

    public function exists(): bool
    {
        return filled($this->external_url)
            || (filled($this->image_path) && MediaAsset::existsAt($this->image_path));
    }
}
