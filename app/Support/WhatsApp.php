<?php

namespace App\Support;

/**
 * WhatsApp links that open a chat with the message ready (wa.me). The app never sends anything itself: the
 * Secretary taps Send in their own WhatsApp, so there is no WhatsApp Business account or cost involved.
 */
class WhatsApp
{
    /** A chat with this member (+27821234567), or null when they have no cellphone number. */
    public static function to(?string $phone, string $text = ''): ?string
    {
        if (blank($phone)) {
            return null;
        }

        // wa.me wants the number in international format without the plus.
        return 'https://wa.me/'.ltrim((string) $phone, '+').($text !== '' ? '?text='.rawurlencode($text) : '');
    }

    /** The message on its own: WhatsApp asks which chat or group to send it to. */
    public static function share(string $text): string
    {
        return 'https://wa.me/?text='.rawurlencode($text);
    }
}
