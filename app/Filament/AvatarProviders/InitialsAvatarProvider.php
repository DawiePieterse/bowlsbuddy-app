<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials drawn locally as an SVG, instead of Filament's default ui-avatars.com images, so members'
 * names are never sent to another website (POPIA).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = collect(preg_split('/\s+/u', trim(Filament::getNameForDefaultAvatar($record))) ?: [])
            ->map(fn (string $word) => mb_strtoupper(mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $word), 0, 1)))
            ->filter()
            ->take(2)
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#248a3d"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" '
            .'font-size="26" font-weight="600" fill="#ffffff">'.e($initials).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
