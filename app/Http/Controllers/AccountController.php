<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MemberPayment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Membership;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * My account: email and password changes, the POPIA data download and account deletion (PLAN.md
 * section 6). Password resets go through the Secretary, so no email is ever sent.
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.edit');
    }

    public function updatePhone(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'current_password' => ['required', 'current_password'],
        ]);

        $phone = Phone::normalize($input['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'Please give a South African cellphone number, like 082 123 4567.',
            ]);
        }

        if (User::query()->where('phone', $phone)->where('uid', '!=', $request->user()->uid)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'An account with this cellphone number already exists.',
            ]);
        }

        $request->user()->update(['phone' => $phone]);

        return back()->with('status', 'Your cellphone number has been changed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'current_password' => ['required', 'current_password'],
        ]);

        $request->user()->update(['pw' => $input['password']]);

        return back()->with('status', 'Your password has been changed.');
    }

    /**
     * Everything we store about the member, as a JSON download.
     */
    public function download(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bookings = Booking::query()
            ->where('uid', $user->uid)
            ->with(['rink', 'reservations'])
            ->get()
            ->map(fn (Booking $booking): array => [
                'rink' => $booking->rink?->name,
                'status' => $booking->status,
                'players' => $booking->quantity,
                'player_names' => $booking->playerNames(),
                'reservations' => $booking->reservations->map(fn (Reservation $reservation): array => [
                    'date' => $reservation->date->format('Y-m-d'),
                    'from' => $reservation->time_start,
                    'to' => $reservation->time_end,
                ])->all(),
            ]);

        return response()->json([
            'name' => $user->fullName(),
            'cellphone' => $user->phone,
            'email' => $user->email,
            'status' => $user->status,
            'member_since' => $user->created?->format('Y-m-d'),
            'membership' => [
                'type' => $user->meta(Membership::TYPE),
                'member_of_the_club_since' => $user->meta(Membership::JOINED),
                'gender' => $user->meta(Membership::GENDER),
                'birthday' => $user->meta(Membership::BIRTHDAY),
                'payments' => $user->payments()->orderBy('paid_on')->get()->map(fn (MemberPayment $payment): array => [
                    'paid_on' => $payment->paid_on->format('Y-m-d'),
                    'membership_year' => $payment->year,
                    'amount' => $payment->amount,
                    'method' => MemberPayment::methodLabel($payment->method),
                    'reference' => $payment->reference,
                ])->all(),
            ],
            'bookings' => $bookings,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="my-data.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Deletes the account and its bookings (POPIA's right to erasure).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        /** @var User $user */
        $user = $request->user();

        Auth::logout();

        DB::transaction(function () use ($user) {
            // Reservations and meta cascade from their tables' foreign keys.
            Booking::query()->where('uid', $user->uid)->get()->each->delete();
            $user->metaEntries()->delete();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Your account has been deleted.');
    }
}
