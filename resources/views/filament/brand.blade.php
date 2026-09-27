{{-- The admin panel's brand: the club logo beside "Bowls Buddy", with the club name underneath, as on the member pages. --}}
<span style="display: flex; align-items: center; gap: 0.6rem;">
    <img src="{{ \App\Support\ClubLogo::url() }}" alt="" style="height: 2.25rem; width: 2.25rem; object-fit: contain;">
    <span style="display: flex; flex-direction: column; line-height: 1.15; text-align: start;">
        <span style="font-weight: 700; font-size: 1.15rem;">Bowls Buddy</span>
        <span style="font-size: 0.75rem; opacity: 0.65;">{{ app(\App\Support\Settings::class)->get('client.name.full') }}</span>
    </span>
</span>
