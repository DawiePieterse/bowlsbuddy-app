<?php

namespace App\Models\Concerns;

use App\Models\Meta\Meta;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Key/value settings stored in a bs_*_meta table next to the model's own table.
 *
 * Using models name their Meta subclass in metaModel(). Load meta for many rows at once with
 * Model::with('metaEntries') to avoid one query per row.
 */
trait HasMeta
{
    /** @return class-string<Meta> */
    abstract public static function metaModel(): string;

    /** @return HasMany<Meta, $this> */
    public function metaEntries(): HasMany
    {
        return $this->hasMany(static::metaModel(), $this->getKeyName(), $this->getKeyName());
    }

    /**
     * The value for $key, preferring the given (or current) locale, then the locale-free value.
     */
    public function meta(string $key, ?string $default = null, ?string $locale = null): ?string
    {
        $entries = $this->metaEntries->where('key', $key);

        if ($entries->isEmpty()) {
            return $default;
        }

        $locale ??= app()->getLocale();

        $entry = $entries->firstWhere('locale', $locale)
            ?? $entries->firstWhere('locale', null)
            ?? $entries->first();

        return $entry->value;
    }

    /**
     * Stores (or with null, removes) the value for $key. The model must already be saved.
     */
    public function setMeta(string $key, ?string $value, ?string $locale = null): void
    {
        $model = static::metaModel();
        $attributes = [$this->getKeyName() => $this->getKey(), 'key' => $key];

        if ($model::HAS_LOCALE) {
            $attributes['locale'] = $locale;
        }

        if ($value === null) {
            $model::query()->where($attributes)->delete();
        } else {
            $model::query()->updateOrCreate($attributes, ['value' => $value]);
        }

        $this->unsetRelation('metaEntries');
    }
}
