<?php

namespace App\Support;

/**
 * The club's four documents - Info, Help, Business Terms and Privacy Policy - each a page members
 * read and the Secretary edits under Settings > Documents. Until the club saves its own text, the
 * example text below is shown and fills the editor; clearing an editor brings the example back.
 */
class StandardTexts
{
    /** document => page title */
    public const DOCUMENTS = [
        'info' => 'Info',
        'help' => 'Help',
        'terms' => 'Business Terms',
        'privacy' => 'Privacy Policy',
    ];

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

    public const TERMS = <<<'HTML'
        <p>These terms apply to everyone who books a practice rink at the club through Bowls Buddy. By registering you agree to them.</p>
        <h2>Bookings</h2>
        <ul>
        <li>Bookings are for club members practising at the club.</li>
        <li>One rink per member per day. The member who books is responsible for the rink during the slot.</li>
        <li>Cancel a booking you cannot use, so another member can play.</li>
        <li>The Club Secretary may close a green, block rinks for events or maintenance, or cancel bookings when needed, and will let members know where possible.</li>
        </ul>
        <h2>On the green</h2>
        <ul>
        <li>Follow the club's rules and dress code, and play in the direction of play for the day.</li>
        <li>Leave the rink as you found it.</li>
        </ul>
        <h2>Your account</h2>
        <ul>
        <li>Keep your password to yourself. The Club Secretary may suspend an account that is misused.</li>
        </ul>
        <h2>Changes</h2>
        <p>The club may change these terms. The current version is always on this page.</p>
        HTML;

    public const PRIVACY = <<<'HTML'
        <p>The club respects your privacy and handles your personal information in line with the Protection of Personal Information Act (POPIA).</p>
        <h2>What we keep</h2>
        <ul>
        <li>Your first name, surname and cellphone number.</li>
        <li>Your bookings: rink, date, time and the names of the players you add.</li>
        <li>Your membership: type, since when, the membership fees you paid and, if you give them, your gender and birthday.</li>
        </ul>
        <h2>Why we keep it</h2>
        <ul>
        <li>To run rink bookings and show logged-in members who is playing on each rink.</li>
        <li>To keep the club's membership and subscription records.</li>
        <li>To contact you about your bookings and club news, and to wish you a happy birthday, by WhatsApp or phone.</li>
        </ul>
        <h2>Who sees it</h2>
        <p>Logged-in members see the names on booked rinks. The Club Secretary and the members who help run bookings see your details. The club does not sell your information or share it with anyone else.</p>
        <h2>How long we keep it</h2>
        <p>For as long as you have an account. Deleting your account removes your details, bookings and payment history.</p>
        <h2>Your rights</h2>
        <ul>
        <li>Download everything we keep about you under My account.</li>
        <li>Delete your account and bookings under My account.</li>
        <li>Ask the Club Secretary to correct your details.</li>
        <li>Lodge a complaint with the Information Regulator if you are unhappy with how your information is handled.</li>
        </ul>
        <h2>Questions</h2>
        <p>Contact the Club Secretary.</p>
        HTML;

    /** The club's own text for a document, or the example text when none is saved. */
    public static function for(string $document): string
    {
        $saved = app(Settings::class)->get('service.'.$document);

        if (filled(trim(strip_tags((string) $saved)))) {
            return (string) $saved;
        }

        return self::example($document);
    }

    public static function example(string $document): string
    {
        return match ($document) {
            'info' => self::INFO,
            'help' => self::HELP,
            'terms' => self::TERMS,
            'privacy' => self::PRIVACY,
            default => throw new \InvalidArgumentException("There is no document called {$document}."),
        };
    }
}
