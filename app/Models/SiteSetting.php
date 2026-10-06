<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasTranslations;

    protected array $translatable = ['value_translations'];

    protected $fillable = [
        'key', 'type', 'value', 'value_translations', 'is_translatable', 'group',
    ];

    protected function casts(): array
    {
        return ['is_translatable' => 'boolean'];
    }

    public function resolved(): mixed
    {
        if ($this->is_translatable) {
            return $this->getTranslation('value_translations');
        }

        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
