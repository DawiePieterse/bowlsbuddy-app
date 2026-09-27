<?php

namespace App\Support;

/**
 * The standard Info and Help page text: what members see until the Secretary writes their own,
 * and what the Settings editors start from. Clearing an editor brings the standard text back.
 */
class StandardTexts
{
    public const INFO = <<<'HTML'
        <h2>Welcome</h2>
        <p>Book a practice rink online with Bowls Buddy. The greens overview shows the coming playing days and how many slots are still free on each green.</p>
        <h2>Club rules for bookings</h2>
        <ul>
        <li>One rink per member per day.</li>
        <li>One member books the rink and names their playing partner.</li>
        <li>Play in the direction shown on the green's calendar for that day.</li>
        <li>Cancel your booking under My bookings if you can't make it, so another member can play.</li>
        </ul>
        <p>Questions? Ask the Club Secretary.</p>
        HTML;

    public const HELP = <<<'HTML'
        <h2>How to book</h2>
        <p>Pick a day and green on the greens overview, tap a free slot, choose 1 or 2 players and confirm. One rink per member per day.</p>
        <h2>How to cancel</h2>
        <p>Under My bookings, up to the cancel cut-off shown when you book.</p>
        <h2>Forgot your password?</h2>
        <p>Ask the Club Secretary to set a temporary one for you.</p>
        HTML;

    /** The club's own text for 'info' or 'help', or the standard text when none is saved. */
    public static function for(string $page): string
    {
        $saved = app(Settings::class)->get('service.'.$page);

        if (filled(trim(strip_tags((string) $saved)))) {
            return (string) $saved;
        }

        return $page === 'help' ? self::HELP : self::INFO;
    }
}
