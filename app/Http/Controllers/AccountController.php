<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function updateEmail(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:bs_users,email,'.$request->user()->uid.',uid'],
            'current_password' => ['required', 'current_password'],
        ], [
            'email.unique' => 'An account with this email address already exists.',
        ]);

        $request->user()->update(['email' => $input['email']]);

        return back()->with('status', 'Your email address has been changed.');
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
            'name' => trim($user->firstName().' '.$user->lastName()) ?: $user->alias,
            'email' => $user->email,
            'status' => $user->status,
            'member_since' => $user->created?->format('Y-m-d'),
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
