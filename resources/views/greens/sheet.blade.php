<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Day sheet - Green {{ $green }} - {{ $day->format('D j M Y') }}</title>
    <style>
        body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #111; margin: 24px; }
        h1 { font-size: 22px; margin: 0; }
        .sub { color: #555; margin: 4px 0 16px; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .qr { width: 110px; }
        .qr svg { width: 110px; height: 110px; }
        .qr .hint { font-size: 11px; color: #555; text-align: center; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: center; height: 34px; vertical-align: middle; }
        th { background: #eee; }
        td.has-event { background: #ede9fe; }
        .print-button { margin: 16px 0; padding: 10px 18px; font-size: 15px; }
        @media print { .print-button { display: none; } body { margin: 8mm; } }
    </style>
</head>
<body>
    @php($time = fn (int $seconds) => sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)))

    <div class="top">
        @if ($clubLogo = \App\Support\ClubLogo::url())
            <img src="{{ $clubLogo }}" alt="" style="height: 56px; width: 56px; object-fit: contain;">
        @endif
        <div style="flex: 1;">
            <h1>Green {{ $green }} &middot; {{ $day->format('l j F Y') }}</h1>
            <p class="sub">Bowls Buddy &middot; {{ app(\App\Support\Settings::class)->get('client.name.full') }} &middot; day sheet{{ $direction ? ' · play '.$direction : '' }}</p>
            @if ($sheet['closed'])
                <p class="sub"><strong>Green {{ $green }} is closed on this day.</strong></p>
            @endif
        </div>
        <div class="qr">
            {!! $qrSvg !!}
            <div class="hint">Live bookings</div>
        </div>
    </div>

    <button class="print-button" onclick="window.print()">Print this sheet</button>

    <table>
        <thead>
            <tr>
                <th>Time</th>
                @foreach ($sheet['rinks'] as $row)
                    <th>{{ $row['rink']->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($sheet['slots'] as $index => [$from, $until])
                <tr>
                    <th>{{ $time($from) }}-{{ $time($until) }}</th>
                    @foreach ($sheet['rinks'] as $row)
                        @php($cell = $row['cells'][$index])
                        <td @class(['has-event' => $cell['events'] !== []])>
                            @foreach ($cell['events'] as $eventName)
                                <div>{{ $eventName }}</div>
                            @endforeach
                            @foreach ($cell['bookings'] as $booking)
                                @foreach (array_merge(
                                    [trim($booking->user->firstName().' '.$booking->user->lastName()) ?: $booking->user->alias],
                                    $booking->playerNames(),
                                ) as $name)
                                    <div>{{ $name }}</div>
                                @endforeach
                            @endforeach
                            @if ($sheet['closed'] && ! $cell['events'] && ! $cell['bookings'])
                                Closed
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
