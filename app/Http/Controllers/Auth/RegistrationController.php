<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /** Submissions faster than this are treated as bots (the plan's anti-bot delay). */
    private const MIN_SECONDS = 3;

    public function create(): View
    {
        return view('auth.register', [
            'openedAt' => Crypt::encryptString((string) now()->getTimestamp()),
        ]);
    }

    public function store(Request $request, Settings $settings): RedirectResponse
    {
        $input = $request->validate([
            'firstname' => ['required', 'string', 'max:100'],
            'lastname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:bs_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accept_terms' => ['accepted'],
            'opened_at' => ['required', 'string'],
            'website' => ['prohibited'], // honeypot: humans never see it
        ], [
            'accept_terms.accepted' => 'Please accept the Business Terms and the Privacy Policy.',
            'email.unique' => 'An account with this email address already exists.',
        ]);

        try {
            $openedAt = (int) Crypt::decryptString($input['opened_at']);
        } catch (DecryptException) {
            $openedAt = 0;
        }

        if ($openedAt <= 0 || now()->getTimestamp() - $openedAt < self::MIN_SECONDS) {
            throw ValidationException::withMessages([
                'email' => 'That was a little quick. Please try again.',
            ]);
        }

        $immediate = $settings->get('service.user.activation', 'immediate') === 'immediate';

        $user = User::query()->create([
            'alias' => trim($input['firstname'].' '.$input['lastname']),
            'status' => $immediate ? 'enabled' : 'disabled',
            'email' => $input['email'],
            'pw' => $input['password'],
        ]);

        $user->setMeta('firstname', trim($input['firstname']));
        $user->setMeta('lastname', trim($input['lastname']));
        $user->setMeta('terms-accepted', now()->format('Y-m-d H:i:s'));

        if (! $immediate) {
            return redirect()->route('login')
                ->with('status', 'Thank you! The Club Secretary will activate your account.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Welcome! Your account is ready.');
    }
}
