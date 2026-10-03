{{-- The brand on every page, in the admin panel's topbar and the member pages' alike: the club logo beside
     "Bowls Buddy", with the club name underneath. Inline styles, as the panel's CSS is precompiled. The member
     layout passes $logoUrl and $clubName, which it has looked up already. --}}
<span style="display: flex; align-items: center; gap: 0.625rem;">
    <img class="logo" src="{{ $logoUrl ?? \App\Support\ClubLogo::url() }}" alt=""
         style="height: 2.25rem; width: 2.25rem; object-fit: contain; border-radius: 0.5rem; background: #fff; flex: none;">
    <span style="display: flex; flex-direction: column; line-height: 1.2; text-align: start; min-width: 0;">
        <span style="font-weight: 700; font-size: 1.0625rem; letter-spacing: -0.01em;">Bowls Buddy</span>
        <span style="font-size: 0.75rem; font-weight: 500; opacity: 0.6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $clubName ?? app(\App\Support\Settings::class)->get('client.name.full') }}</span>
    </span>
</span>
