<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Minimal translatable-attribute support backed by JSON columns.
 *
 * Usage:
 *   protected array $translatable = ['title', 'description'];
 *
 * Reading  $model->title      returns the string for the current locale,
 *                             falling back to the app fallback locale, then to
 *                             the first non-empty value present.
 * Reading  $model->getTranslations('title')  returns the full array.
 * Writing  $model->title = 'Hi'              sets the current locale only.
 * Writing  $model->title = ['id' => 'Hai', 'en' => 'Hi']  sets both.
 *
 * WHY NOT spatie/laravel-translatable: this is ~60 lines, has no version
 * surface to drift, and is transparent if you are still learning Eloquent.
 * It is deliberately API-compatible with the spatie package's common methods
 * (getTranslation / setTranslation / getTranslations), so swapping to it later
 * is a matter of deleting this trait and adding theirs. If you need per-locale
 * querying or fallback chains, switch -- do not grow this file.
 */
trait HasTranslations
{
    public function translatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    protected function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->translatableAttributes(), true);
    }

    public function getAttribute($key)
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::getAttribute($key);
        }

        return $this->getTranslation($key, app()->getLocale());
    }

    public function setAttribute($key, $value)
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::setAttribute($key, $value);
        }

        if (is_array($value)) {
            return $this->setTranslations($key, $value);
        }

        return $this->setTranslation($key, app()->getLocale(), $value);
    }

    public function getTranslations(string $key): array
    {
        // Current attribute value FIRST, so a pending change is visible before
        // save; getRawOriginal() holds the value as loaded from the database and
        // would return stale JSON on a dirty model.
        $raw = $this->attributes[$key] ?? $this->getRawOriginal($key) ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            // A plain string in a translatable column means legacy data that
            // predates translation. Treat it as the fallback locale.
            if (! is_array($decoded)) {
                return [config('app.fallback_locale') => $raw];
            }

            return $decoded;
        }

        return [];
    }

    public function getTranslation(string $key, ?string $locale = null): ?string
    {
        $translations = $this->getTranslations($key);
        $locale ??= app()->getLocale();

        foreach ([$locale, config('app.fallback_locale')] as $candidate) {
            if (isset($translations[$candidate]) && $translations[$candidate] !== '') {
                return $translations[$candidate];
            }
        }

        // Last resort: any non-empty value, so a half-translated record still
        // renders something rather than a blank page.
        foreach ($translations as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function setTranslation(string $key, string $locale, ?string $value): static
    {
        $translations = $this->getTranslations($key);
        $translations[$locale] = $value;

        return $this->setTranslations($key, $translations);
    }

    public function setTranslations(string $key, array $translations): static
    {
        $this->attributes[$key] = json_encode(
            array_filter($translations, fn ($v) => $v !== null),
            JSON_UNESCAPED_UNICODE
        );

        return $this;
    }

    /**
     * Include resolved translatable attributes in toArray()/toJson() output,
     * so API responses and Blade @json() do not leak raw JSON blobs.
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach ($this->translatableAttributes() as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $this->getTranslation($key);
            }
        }

        return $attributes;
    }
}
