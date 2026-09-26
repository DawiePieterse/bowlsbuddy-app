<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Rink;
use App\Services\BookingRefused;
use App\Services\BookingRules;
use App\Services\BookingService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * The booking form for one slot: players, partner's name, rules acceptance.
     */
    public function create(Request $request, BookingRules $rules): View
    {
        [$rink, $start, $end] = $this->slotFromRequest($request);

        $reason = $rules->refusal($rink, $start, $end, $request->user())?->message();

        return view('bookings.create', [
            'rink' => $rink,
            'start' => $start,
            'end' => $end,
            'reason' => $reason,
            'maxPlayers' => min(2, $rink->capacity),
            'rulesText' => $rink->meta('rules.text'),
        ]);
    }

    public function store(Request $request, BookingService $service): RedirectResponse
    {
        [$rink, $start, $end] = $this->slotFromRequest($request);

        $input = $request->validate([
            'players' => ['required', 'integer', 'min:1', 'max:'.min(2, $rink->capacity)],
            'partner' => ['required_if:players,2', 'nullable', 'string', 'max:100'],
            'accept_rules' => $rink->meta('rules.text') ? ['accepted'] : [],
        ], [
            'partner.required_if' => "Please give your partner's name.",
            'accept_rules.accepted' => 'Please read and accept the rules.',
        ]);

        $partner = trim($input['partner'] ?? '');

        if ((int) $input['players'] === 2 && (strlen($partner) < 5 || ! str_contains($partner, ' '))) {
            throw ValidationException::withMessages([
                'partner' => 'The full first and last name of your partner is required.',
            ]);
        }

        try {
            $booking = $service->book(
                $request->user(),
                $rink,
                $start,
                $end,
                players: (int) $input['players'],
                playerNames: (int) $input['players'] === 2 ? [$partner] : [],
            );
        } catch (BookingRefused $refused) {
            return back()->with('warning', $refused->getMessage())->withInput();
        }

        return redirect()
            ->route('bookings.confirmation', $booking)
            ->with('status', 'Your rink has been booked!');
    }

    /**
     * The just-booked confirmation, with the WhatsApp share link.
     */
    public function confirmation(Request $request, Settings $settings, Booking $booking): View
    {
        abort_unless($booking->uid === $request->user()->uid, 403);

        $booking->load(['rink', 'reservations', 'metaEntries']);

        $reservation = $booking->reservations->first();
        $start = CarbonImmutable::parse($reservation->date->format('Y-m-d').' '.$reservation->time_start);

        $message = sprintf(
            'I booked rink %s at %s for %s, %s-%s.%s',
            $booking->rink->name,
            $settings->get('client.name.short', $settings->get('client.name.full', 'our club')),
            $start->format('D j M Y'),
            $start->format('H:i'),
            substr($reservation->time_end, 0, 5),
            $booking->playerNames() ? ' Playing with '.implode(', ', $booking->playerNames()).'.' : '',
        );

        return view('bookings.confirmation', [
            'booking' => $booking,
            'start' => $start,
            'whatsappUrl' => 'https://wa.me/?text='.rawurlencode($message),
        ]);
    }

    /**
     * The member's own bookings, upcoming first.
     */
    public function index(Request $request, BookingRules $rules): View
    {
        $bookings = Booking::query()
            ->where('uid', $request->user()->uid)
            ->with(['rink', 'reservations', 'metaEntries'])
            ->get()
            ->sortByDesc(fn (Booking $booking) => $booking->reservations->first()?->date->format('Y-m-d')
                .' '.$booking->reservations->first()?->time_start)
            ->values();

        return view('bookings.index', [
            'bookings' => $bookings,
            'cancellable' => $bookings->mapWithKeys(fn (Booking $booking) => [
                $booking->bid => $rules->canCancel($booking, $request->user()),
            ]),
        ]);
    }

    public function cancel(Request $request, BookingService $service, Booking $booking): RedirectResponse
    {
        abort_unless(
            $booking->uid === $request->user()->uid || $request->user()->hasPrivilege('calendar.cancel-single-bookings'),
            403,
        );

        try {
            $service->cancel($booking, $request->user());
        } catch (BookingRefused $refused) {
            return back()->with('warning', $refused->getMessage());
        }

        return back()->with('status', 'Your booking has been cancelled.');
    }

    /**
     * @return array{Rink, CarbonImmutable, CarbonImmutable}
     */
    private function slotFromRequest(Request $request): array
    {
        $rink = Rink::query()->find($request->query('rink') ?? $request->input('rink'));

        abort_unless($rink !== null, 404);

        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', (string) ($request->query('start') ?? $request->input('start')));
        } catch (InvalidFormatException) {
            abort(404);
        }

        return [$rink, $start, $start->addSeconds($rink->time_block)];
    }
}
