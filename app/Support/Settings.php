<?php

namespace App\Support;

use App\Models\Option;
use Illuminate\Support\Facades\Cache;

/**
 * Site settings from bs_options, cached until the next change. A value for the current locale wins over
 * the locale-free one. The cache is a database table on shared hosting, so the values are also kept in
 * memory for the request: pages read settings for every row they show.
 */
class Settings
{
    private const CACHE_KEY = 'settings';

    /** @var array<string, array<string, string>>|null */
    private ?array $all = null;

    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->all();
        $locale = app()->getLocale();

        return $all[$locale][$key] ?? $all[''][$key] ?? $default;
    }

    /** The club's short name, else its full name, for messages ("...at LCE"). */
    public function clubName(string $default = 'the club'): string
    {
        return $this->get('client.name.short') ?? $this->get('client.name.full') ?? $default;
    }

    public function set(string $key, ?string $value, ?string $locale = null): void
    {
        if ($value === null) {
            Option::query()->where('key', $key)->where('locale', $locale)->delete();
        } else {
            Option::query()->updateOrCreate(['key' => $key, 'locale' => $locale], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->all = null;
    }

    /** @return array<string, array<string, string>> locale ('' for none) => key => value */
    private function all(): array
    {
        return $this->all ??= Cache::rememberForever(self::CACHE_KEY, function () {
            $all = [];

            foreach (Option::query()->get(['key', 'value', 'locale']) as $option) {
                $all[$option->locale ?? ''][$option->key] = $option->value;
            }

            return $all;
        });
    }
}
