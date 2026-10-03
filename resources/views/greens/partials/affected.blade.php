@use('App\Filament\Pages\AffectedBookings')
@use('App\Services\DisplacedBookings')
@use('App\Support\Phone')
{{-- The Secretary's list of members whose bookings on this green that day a closure cancelled, each with a
     WhatsApp message ready to send from the Secretary's own phone. Tapping Send also notes the member as
     told (in the background, so WhatsApp still opens straight away). --}}
@php($untold = count(array_filter($messages, fn (array $message) => ! $message['told'])))
<div class="card affected" id="affected">
    <h2>Cancelled bookings</h2>
    <p class="muted">
        @if ($untold > 0)
            Let {{ $untold }} {{ Str::plural('member', $untold) }} know. Send opens WhatsApp with a message ready;
            you can change it before you send it.
        @else
            Everyone has been told.
        @endif
    </p>
    <ul class="affected-list">
        @foreach ($messages as $message)
            <li @class(['told' => $message['told']])>
                <div class="who">
                    <strong>{{ $message['name'] }}</strong>
                    <span class="muted">&middot; {{ $message['phone'] ? Phone::pretty($message['phone']) : 'no cellphone number'.($message['user']->email ? ' ('.$message['user']->email.')' : '') }}</span>
                    <span class="told-mark">{{ svg('heroicon-o-check', 'icon') }}Told</span>
                    @foreach ($message['items'] as $item)
                        <div class="muted">{{ ucfirst(DisplacedBookings::describe($item)) }}: {{ $item['reason'] }}</div>
                    @endforeach
                </div>
                @if ($message['url'])
                    <a class="button whatsapp small" href="{{ $message['url'] }}" target="_blank" rel="noopener"
                       data-told="{{ $message['bookings'] }}"
                       aria-label="Send {{ $message['name'] }} a WhatsApp message">@include('partials.whatsapp-icon')Send</a>
                @elseif (! $message['told'])
                    <form method="POST" action="{{ route('affected.told') }}">
                        @csrf
                        <input type="hidden" name="bookings" value="{{ $message['bookings'] }}">
                        <button type="submit" class="subtle small">Mark as told</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
    @if (AffectedBookings::canAccess())
        <p class="links"><a href="{{ AffectedBookings::getUrl(panel: 'admin') }}">All cancelled bookings to let members know about</a></p>
    @endif
</div>
<script>
    // Sending the WhatsApp message counts as telling the member: note it without holding up the link.
    document.querySelectorAll('#affected [data-told]').forEach(function (link) {
        link.addEventListener('click', function () {
            fetch(@js(route('affected.told')), {
                method: 'POST',
                keepalive: true,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify({ bookings: link.dataset.told }),
            });
            link.closest('li').classList.add('told');
        });
    });
</script>
