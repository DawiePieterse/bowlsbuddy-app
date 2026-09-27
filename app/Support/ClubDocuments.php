<?php

namespace App\Support;

/**
 * The PDF documents the Secretary can upload (Settings > Documents), stored as
 * storage/app/documents/<name>.pdf and served through /documents/<name>.
 */
class ClubDocuments
{
    /** name => label */
    public const ALL = [
        'info' => 'Info sheet',
        'help' => 'Help guide',
        'terms' => 'Business Terms',
        'privacy' => 'Privacy Policy',
    ];

    public static function path(string $name): string
    {
        return storage_path('app/documents/'.$name.'.pdf');
    }

    public static function exists(string $name): bool
    {
        return array_key_exists($name, self::ALL) && is_file(self::path($name));
    }

    /** Deletes the document's PDF, if there is one. */
    public static function remove(string $name): void
    {
        if (self::exists($name)) {
            @unlink(self::path($name));
        }
    }
}
