<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Only active accounts may log in. Old, weaker password hashes are upgraded automatically.
        $loggedIn = Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            fn (Builder $query) => $query->whereIn('status', User::LOGIN_STATUSES),
        ], $request->boolean('remember'));

        if (! $loggedIn) {
            throw ValidationException::withMessages([
                'email' => 'These details are not correct, or the account is not active yet.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
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
