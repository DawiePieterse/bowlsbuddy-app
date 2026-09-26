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
        td.state-event { background: #eadcf5; }
        td.state-closed { background: #f6dada; }
        .print-button { margin: 16px 0; padding: 10px 18px; font-size: 15px; }
        @media print { .print-button { display: none; } body { margin: 8mm; } }
    </style>
</head>
<body>
    <div class="top">
        <div>
            <h1>Green {{ $green }} &middot; {{ $day->format('l j F Y') }}</h1>
            <p class="sub">{{ app(\App\Support\Settings::class)->get('client.name.full') }} &middot; day sheet</p>
            @if ($closed)
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
                @foreach ($rinks as $rink)
                    <th>{{ $rink->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($grid as $row)
                <tr>
                    <th>{{ $row['time'] }}</th>
                    @foreach ($row['cells'] as $cell)
                        <td class="state-{{ $cell['state'] }}">
                            @if (in_array($cell['state'], ['own', 'booked'], true))
                                {{ $cell['label'] }}
                            @elseif ($cell['state'] === 'event')
                                {{ $cell['label'] }}
                            @elseif ($cell['state'] === 'closed')
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
