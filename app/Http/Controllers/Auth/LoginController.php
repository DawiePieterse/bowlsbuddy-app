<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
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

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
        ]);

        $phone = PhoneNumber::normalize($credentials['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Please enter your mobile number, e.g. 082 123 4567.']);
        }

        // Only active accounts may log in. Old, weaker password hashes are upgraded automatically.
        $loggedIn = Auth::attempt([
            'phone' => $phone,
            'password' => $credentials['password'],
            fn (Builder $query) => $query->whereIn('status', User::LOGIN_STATUSES),
        ], $request->boolean('remember'));

        if (! $loggedIn) {
            throw ValidationException::withMessages(['phone' => $this->failureMessage($phone, $credentials['password'])]);
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

    /** Tells a member with the right password that the Secretary still has to activate the account. */
    private function failureMessage(string $phone, string $password): string
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user?->isAwaitingActivation() && Hash::check($password, (string) $user->pw)) {
            return 'Your account is waiting for the Club Secretary to activate it.';
        }

        return 'These details are not correct, or the account is not active.';
    }
}
