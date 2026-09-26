<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Reservation;
use App\Models\Rink;
use App\Models\User;
use App\Services\ClubSetup;
use App\Services\GreenService;
use App\Services\GreensOverview;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GreensController extends Controller
{
    /**
     * The greens overview: the next 14 playing days with free slots, closures and events per green.
     */
    public function index(GreensOverview $overview): View|RedirectResponse
    {
        // A fresh install goes to the first-run setup page.
        if (! app(ClubSetup::class)->isSetUp()) {
            return redirect()->route('setup');
        }

        return view('greens.index', ['days' => $overview->days(14)]);
    }

    /**
     * One green's calendar for one day: its rinks by hourly slots. Player names show for
     * logged-in members only; the member's own bookings show green.
     */
    public function show(Request $request, GreenService $greens, GreensOverview $overview, string $green, ?string $date = null): View
    {
        abort_unless(in_array($green, $greens->greens(), true), 404);

        $days = $greens->playingDays(14);

        abort_if($days === [], 404);

        try {
            $day = $date === null ? $days[0] : CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (InvalidFormatException) {
            abort(404);
        }

        $rinks = Rink::query()->visible()->orderBy('priority')->get()
            ->filter(fn (Rink $rink) => $rink->green() === $green)
            ->values();

        abort_if($rinks->isEmpty(), 404);

        $dayIndex = collect($days)->search(fn (CarbonImmutable $other) => $other->isSameDay($day));

        return view('greens.show', [
            'green' => $green,
            'greens' => $greens->greens(),
            'day' => $day,
            'rinks' => $rinks,
            'closed' => $greens->isClosed($green, $day),
            'hidden' => $greens->isHiddenDay($day),
            'grid' => $this->grid($request, $overview, $rinks, $day, $greens->isClosed($green, $day)),
            'previousDay' => $dayIndex !== false && $dayIndex > 0 ? $days[$dayIndex - 1] : null,
            'nextDay' => $dayIndex !== false && $dayIndex < count($days) - 1 ? $days[$dayIndex + 1] : null,
        ]);
    }

    /**
     * The Secretary closes this green for the day (the plan's green open/close, privilege
     * "admin.event" like other blocked time).
     */
    public function close(Request $request, GreenService $greens, string $green, string $date): RedirectResponse
    {
        [$green, $day] = $this->greenDay($request, $greens, $green, $date);

        $greens->close($green, $day);

        return redirect()->route('greens.show', [$green, $date])
            ->with('status', 'Green '.$green.' is now closed on '.$day->format('D j M').'.');
    }

    public function open(Request $request, GreenService $greens, string $green, string $date): RedirectResponse
    {
        [$green, $day] = $this->greenDay($request, $greens, $green, $date);

        $greens->open($green, $day);

        return redirect()->route('greens.show', [$green, $date])
            ->with('status', 'Green '.$green.' is open again on '.$day->format('D j M').'.');
    }

    /**
     * The printable day sheet (PLAN.md section 7): rinks by hour with player names, events and
     * closed greens, plus a QR code to the live calendar.
     */
    public function sheet(Request $request, GreenService $greens, GreensOverview $overview, string $green, string $date): View
    {
        abort_unless(in_array($green, $greens->greens(), true), 404);

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (InvalidFormatException) {
            abort(404);
        }

        $rinks = Rink::query()->visible()->orderBy('priority')->get()
            ->filter(fn (Rink $rink) => $rink->green() === $green)
            ->values();

        abort_if($rinks->isEmpty(), 404);

        $liveUrl = route('greens.show', [$green, $date]);

        return view('greens.sheet', [
            'green' => $green,
            'day' => $day,
            'rinks' => $rinks,
            'closed' => $greens->isClosed($green, $day),
            'grid' => $this->grid($request, $overview, $rinks, $day, $greens->isClosed($green, $day)),
            'liveUrl' => $liveUrl,
            'qrSvg' => $this->qrSvg($liveUrl),
        ]);
    }

    /** @return array{string, CarbonImmutable} */
    private function greenDay(Request $request, GreenService $greens, string $green, string $date): array
    {
        abort_unless($request->user()?->hasPrivilege('admin.event'), 403);
        abort_unless(in_array($green, $greens->greens(), true), 404);

        try {
            return [$green, CarbonImmutable::createFromFormat('!Y-m-d', $date)];
        } catch (InvalidFormatException) {
            abort(404);
        }
    }

    private function qrSvg(string $url): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($url);
    }

    /**
     * One row per slot, one cell per rink: free (with booking link), booked (players), own,
     * event, past or closed.
     *
     * @param  Collection<int, Rink>  $rinks
     * @return list<array{time: string, cells: list<array<string, mixed>>}>
     */
    private function grid(Request $request, GreensOverview $overview, $rinks, CarbonImmutable $day, bool $closed): array
    {
        $user = $request->user();

        // The day's bookings on these rinks, with who booked and the player names.
        $reservations = Reservation::query()
            ->where('date', $day->format('Y-m-d'))
            ->whereHas('booking', fn ($query) => $query
                ->whereIn('sid', $rinks->pluck('sid'))
                ->where('visibility', 'public')
                ->where('status', '!=', 'cancelled'))
            ->with(['booking.user.metaEntries', 'booking.metaEntries'])
            ->get();

        $events = Event::query()
            ->with('metaEntries')
            ->where('status', 'enabled')
            ->where('datetime_start', '<', $day->addDay())
            ->where('datetime_end', '>', $day)
            ->get();

        $grid = [];

        foreach ($overview->slots($rinks->first(), $day) as [$start, $end]) {
            $cells = [];

            foreach ($rinks as $rink) {
                $cells[] = $this->cell($user, $rink, $start, $end, $reservations, $events, $closed);
            }

            $grid[] = ['time' => $start->format('H:i').'-'.$end->format('H:i'), 'cells' => $cells];
        }

        return $grid;
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @param  Collection<int, Event>  $events
     * @return array<string, mixed>
     */
    private function cell(?User $user, Rink $rink, CarbonImmutable $start, CarbonImmutable $end, Collection $reservations, Collection $events, bool $closed): array
    {
        $event = $events->first(fn (Event $event) => $event->covers($rink)
            && $event->datetime_start < $end && $event->datetime_end > $start);

        if ($event) {
            return ['state' => 'event', 'label' => $event->meta('name', 'Event')];
        }

        if ($closed) {
            return ['state' => 'closed'];
        }

        $booked = $reservations->first(fn (Reservation $reservation) => $reservation->booking->sid === $rink->sid
            && $reservation->time_start < $end->format('H:i:s')
            && $reservation->time_end > $start->format('H:i:s'));

        if ($booked) {
            $booking = $booked->booking;
            $names = [];

            if ($user !== null) {
                $names = array_merge(
                    [trim($booking->user->firstName().' '.$booking->user->lastName()) ?: $booking->user->alias],
                    $booking->playerNames(),
                );
            }

            return [
                'state' => $user !== null && $booking->uid === $user->uid ? 'own' : 'booked',
                'label' => $names ? implode(', ', $names) : 'Booked',
            ];
        }

        if ($start < now()->subSeconds((int) ($rink->time_block_bookable / 2))) {
            return ['state' => 'past'];
        }

        return [
            'state' => 'free',
            'url' => route('bookings.create', ['rink' => $rink->sid, 'start' => $start->format('Y-m-d H:i')]),
        ];
    }
}
