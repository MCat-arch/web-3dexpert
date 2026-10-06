<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFeature extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['label'];

    protected $fillable = ['service_id', 'label', 'is_heading', 'sort_order'];

    protected function casts(): array
    {
        return ['is_heading' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
