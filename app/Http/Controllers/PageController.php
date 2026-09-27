<?php

namespace App\Http\Controllers;

use App\Support\ClubLogo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageController extends Controller
{
    /**
     * The club logo the Secretary uploaded, shown on every page.
     */
    public function logo(): BinaryFileResponse
    {
        $path = ClubLogo::path();

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => ClubLogo::mime(),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
