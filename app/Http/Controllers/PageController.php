<?php

namespace App\Http\Controllers;

use App\Support\ClubDocuments;
use App\Support\ClubLogo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageController extends Controller
{
    /**
     * A PDF the Secretary uploaded (info sheet, help guide, Business Terms, Privacy Policy). Served
     * through a route because shared hosts may not allow the storage symlink (PLAN.md 9.1).
     */
    public function document(string $document): BinaryFileResponse
    {
        abort_unless(ClubDocuments::exists($document), 404);

        return response()->file(ClubDocuments::path($document), ['Content-Type' => 'application/pdf']);
    }

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
