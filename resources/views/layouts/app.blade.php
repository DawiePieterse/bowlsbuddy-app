<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Bowls Buddy' }} - {{ app(\App\Support\Settings::class)->get('client.name.full', 'Bowls Buddy') }}</title>
    <style>
        :root { --text: #1d1d1f; --muted: #6e6e73; --bg: #f5f5f7; --card: #fff; --line: #d2d2d7; --green: #34c759; --blue: #0071e3; --red: #d70015; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--text); background: var(--bg); }
        header { background: #1d1d1f; color: #fff; padding: 16px 24px; }
        header .club { color: #a1a1a6; font-size: 13px; }
        header .name { font-size: 20px; font-weight: 700; }
        main { max-width: 480px; margin: 32px auto; padding: 0 16px; }
        .card { background: var(--card); border-radius: 14px; padding: 24px; box-shadow: 0 4px 16px rgba(0,0,0,.06); }
        h1 { font-size: 24px; margin: 0 0 16px; }
        label { display: block; font-size: 14px; margin: 12px 0 4px; }
        input[type=email], input[type=password] { width: 100%; padding: 10px 12px; font-size: 16px; border: 1px solid var(--line); border-radius: 8px; }
        .check { display: flex; gap: 8px; align-items: center; margin-top: 12px; }
        button { margin-top: 20px; width: 100%; padding: 12px; font-size: 16px; font-weight: 600; color: #fff; background: var(--blue); border: 0; border-radius: 8px; cursor: pointer; }
        .error { color: var(--red); font-size: 14px; margin-top: 6px; }
        .muted { color: var(--muted); font-size: 14px; }
        a { color: #0066cc; }
    </style>
</head>
<body>
    <header>
        <div class="club">{{ app(\App\Support\Settings::class)->get('client.name.full') }}</div>
        <div class="name">Bowls Buddy</div>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
