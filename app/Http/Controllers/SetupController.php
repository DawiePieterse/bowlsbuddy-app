<?php

namespace App\Http\Controllers;

use App\Services\ClubSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The first-run setup page (PLAN.md Phase 4): the same questions as club:create, for hosting
 * without a command line. Only reachable while the install has no club yet.
 */
class SetupController extends Controller
{
    public function create(ClubSetup $setup): View
    {
        abort_if($setup->isSetUp(), 404);

        return view('setup', ['defaults' => config('club')]);
    }

    public function store(Request $request, ClubSetup $setup): RedirectResponse
    {
        abort_if($setup->isSetUp(), 404);

        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:20'],
            'greens' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 ]+(,[A-Za-z0-9 ]+)*$/'],
            'rinks_per_green' => ['required', 'integer', 'min:1', 'max:20'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'slot_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'players_per_rink' => ['required', 'integer', 'min:1', 'max:8'],
            'booking_range_days' => ['required', 'integer', 'min:1', 'max:60'],
            'cancel_range_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $input['greens'] = array_map('trim', explode(',', $input['greens']));

        $setup->create($input + ['admin_password' => $input['admin_password']]);

        return redirect()->route('login')
            ->with('status', 'Your club is ready. Log in as the Secretary with the details you just chose.');
    }
}
