<?php

namespace App\Http\Controllers;

use App\Services\DisplacedBookings;
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
        abort_unless($request->user()?->hasPrivilege('admin.event'), 403);

        $displaced->markTold(array_map('intval', array_filter(explode(',', (string) $request->input('bookings')))));

        return $request->expectsJson() ? response()->noContent() : back();
    }
}
