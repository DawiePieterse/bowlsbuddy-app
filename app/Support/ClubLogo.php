<?php

namespace App\Support;

/**
 * The club's logo, uploaded by the Secretary (Settings > Names and text) into
 * storage/app/documents as logo.<ext>. It is served through the /logo route because shared
 * hosts may not allow the storage symlink, and shown in the header of every page, on the day
 * sheet and as the panel's brand mark.
 */
class ClubLogo
{
    private const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

    /** The logo's absolute path, or null when no logo has been uploaded. */
    public static function path(): ?string
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = storage_path('app/documents/logo.'.$extension);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function exists(): bool
    {
        return self::path() !== null;
    }

    /**
     * The URL every page uses: the uploaded club logo (cache-busted so a new upload shows right
     * away), or the bundled Bowls Buddy mark until one is uploaded.
     */
    public static function url(): string
    {
        $path = self::path();

        return $path === null ? asset('img/logo.png') : route('logo').'?v='.filemtime($path);
    }

    public static function mime(): string
    {
        return match (pathinfo((string) self::path(), PATHINFO_EXTENSION)) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    /** Removes every stored logo file except $basename (null removes them all). */
    public static function keepOnly(?string $basename): void
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = storage_path('app/documents/logo.'.$extension);

            if (is_file($path) && basename($path) !== $basename) {
                @unlink($path);
            }
        }
    }
}
