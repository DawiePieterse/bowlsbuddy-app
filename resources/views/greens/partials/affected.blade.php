@use('App\Filament\Pages\AffectedBookings')
@use('App\Services\DisplacedBookings')
@use('App\Support\Phone')
{{-- The Secretary's list of members whose bookings on this green that day can't go ahead, each with a
     WhatsApp message ready to send from the Secretary's own phone. --}}
<div class="card affected" id="affected">
    <h2>Let {{ count($messages) }} {{ Str::plural('member', count($messages)) }} know</h2>
    <p class="muted">{{ count($messages) === 1 ? 'This booking' : 'These bookings' }} can't go ahead. Send opens WhatsApp
        with a message ready; you can change it before you send it.</p>
    <ul class="affected-list">
        @foreach ($messages as $message)
            <li>
                <div class="who">
                    <strong>{{ $message['name'] }}</strong>
                    <span class="muted">&middot; {{ $message['phone'] ? Phone::pretty($message['phone']) : 'no cellphone number'.($message['user']->email ? ' ('.$message['user']->email.')' : '') }}</span>
                    @foreach ($message['items'] as $item)
                        <div class="muted">{{ ucfirst(DisplacedBookings::describe($item)) }}: {{ $item['reason'] }}</div>
                    @endforeach
                </div>
                @if ($message['url'])
                    <a class="button whatsapp small" href="{{ $message['url'] }}" target="_blank" rel="noopener"
                       aria-label="Send {{ $message['name'] }} a WhatsApp message">@include('partials.whatsapp-icon')Send</a>
                @endif
            </li>
        @endforeach
    </ul>
    @if (AffectedBookings::canAccess())
        <p class="links"><a href="{{ AffectedBookings::getUrl(panel: 'admin') }}">All bookings that can't go ahead</a></p>
    @endif
</div>
