<?php

namespace App\Http\Controllers;

use App\Filament\Pages\AffectedBookings;
use App\Services\DisplacedBookings;
use App\Support\Ids;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AffectedBookingsController extends Controller
{
    /**
     * The Secretary has let a member know their bookings were cancelled by a closure: tapped Send on WhatsApp
     * (posted in the background) or Mark as told. "bookings" holds the booking ids, comma-separated.
     */
    public function told(Request $request, DisplacedBookings $displaced): Response|RedirectResponse
    {
        abort_unless(AffectedBookings::canAccess(), 403);

        $displaced->markTold(Ids::parse((string) $request->input('bookings')));

        return $request->expectsJson() ? response()->noContent() : back();
    }
}
