<?php

namespace App\Support;

use App\Models\Option;
use Illuminate\Support\Facades\Cache;

/**
 * Site settings from bs_options, cached until the next change. A value for the current locale wins over
 * the locale-free one.
 */
class Settings
{
    private const CACHE_KEY = 'settings';

    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->all();
        $locale = app()->getLocale();

        return $all[$locale][$key] ?? $all[''][$key] ?? $default;
    }

    public function set(string $key, ?string $value, ?string $locale = null): void
    {
        if ($value === null) {
            Option::query()->where('key', $key)->where('locale', $locale)->delete();
        } else {
            Option::query()->updateOrCreate(['key' => $key, 'locale' => $locale], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array<string, string>> locale ('' for none) => key => value */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $all = [];

            foreach (Option::query()->get(['key', 'value', 'locale']) as $option) {
                $all[$option->locale ?? ''][$option->key] = $option->value;
            }

            return $all;
        });
    }
}
