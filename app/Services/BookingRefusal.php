<?php

namespace App\Services;

/**
 * Why a slot can't be booked. The messages follow the original app's wording.
 */
enum BookingRefusal: string
{
    case RinkUnavailable = 'rink-unavailable';
    case InvalidTime = 'invalid-time';
    case OutsidePlayingHours = 'outside-playing-hours';
    case InThePast = 'in-the-past';
    case TooShortNotice = 'too-short-notice';
    case TooFarAhead = 'too-far-ahead';
    case TooLong = 'too-long';
    case DayHidden = 'day-hidden';
    case GreenClosed = 'green-closed';
    case MaxActiveBookings = 'max-active-bookings';
    case Occupied = 'occupied';
    case OneRinkPerDay = 'one-rink-per-day';
    case Event = 'event';
    case InvalidPlayers = 'invalid-players';
    case TooManyPlayers = 'too-many-players';

    public function message(): string
    {
        return match ($this) {
            self::RinkUnavailable => 'This rink is currently not available.',
            self::InvalidTime => 'The chosen time is not a valid slot.',
            self::OutsidePlayingHours => 'The chosen time is outside the playing hours.',
            self::InThePast => 'This time is already over.',
            self::TooShortNotice => 'This time is too soon to book.',
            self::TooFarAhead => 'This date is still too far away.',
            self::TooLong => 'You cannot book this many slots at once.',
            self::DayHidden => 'This day is not open for bookings.',
            self::GreenClosed => 'This green is closed on this day.',
            self::MaxActiveBookings => 'You already have the most bookings you can have open at the same time.',
            self::Occupied => 'This rink is already occupied.',
            self::OneRinkPerDay => 'You can only book one rink per day. You already have a booking on this day.',
            self::Event => 'This rink is taken by an event.',
            self::InvalidPlayers => 'Choose at least one player.',
            self::TooManyPlayers => 'Too many players for this rink.',
        };
    }
}
