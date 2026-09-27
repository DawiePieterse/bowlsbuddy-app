<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Members log in with their cellphone (WhatsApp) number; staff and older accounts may still
     * use an email address. Only active accounts get in; old, weaker password hashes are
     * upgraded on the way.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $phone = Phone::normalize($credentials['login']);

        $user = User::query()
            ->whereIn('status', User::LOGIN_STATUSES)
            ->when(
                $phone !== null,
                fn ($query) => $query->where('phone', $phone),
                fn ($query) => $query->where('email', $credentials['login']),
            )
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->pw)) {
            throw ValidationException::withMessages([
                'login' => 'These details are not correct, or the account is not active yet.',
            ]);
        }

        if (Hash::needsRehash($user->pw)) {
            $user->forceFill(['pw' => $credentials['password']])->save();
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $user->forceFill(['last_activity' => now(), 'last_ip' => $request->ip()])->save();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
