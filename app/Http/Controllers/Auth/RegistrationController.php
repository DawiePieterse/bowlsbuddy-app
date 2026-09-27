<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Members register with their cellphone (WhatsApp) number, an email address, or both. Most clubs
 * run on WhatsApp, but some members have no smartphone and only use email. Either one logs in.
 */
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
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email', 'max:128'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accept_terms' => ['accepted'],
            'opened_at' => ['required', 'string'],
            'website' => ['prohibited'], // honeypot: humans never see it
        ], [
            'accept_terms.accepted' => 'Please accept the Business Terms and the Privacy Policy.',
            'phone.required_without' => 'Please give a cellphone number or an email address.',
            'email.required_without' => 'Please give a cellphone number or an email address.',
            'email.email' => 'Please give a valid email address, like jane@example.com.',
        ]);

        $phone = null;

        if (filled($input['phone'] ?? null)) {
            $phone = Phone::normalize($input['phone']);

            if ($phone === null) {
                throw ValidationException::withMessages([
                    'phone' => 'Please give a South African cellphone number, like 082 123 4567.',
                ]);
            }

            if (User::query()->where('phone', $phone)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'An account with this cellphone number already exists.',
                ]);
            }
        }

        $email = filled($input['email'] ?? null) ? mb_strtolower(trim($input['email'])) : null;

        if ($email !== null && User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account with this email address already exists.',
            ]);
        }

        try {
            $openedAt = (int) Crypt::decryptString($input['opened_at']);
        } catch (DecryptException) {
            $openedAt = 0;
        }

        if ($openedAt <= 0 || now()->getTimestamp() - $openedAt < self::MIN_SECONDS) {
            throw ValidationException::withMessages([
                'phone' => 'That was a little quick. Please try again.',
            ]);
        }

        $immediate = $settings->get('service.user.activation', 'manual') === 'immediate';

        $user = User::query()->create([
            'alias' => trim($input['firstname'].' '.$input['lastname']),
            'status' => $immediate ? 'enabled' : 'disabled',
            'phone' => $phone,
            'email' => $email,
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
