<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageController extends Controller
{
    /**
     * The Business Terms or Privacy Policy PDF, uploaded by the Secretary (Phase 4). Served
     * through a route because shared hosts may not allow the storage symlink (PLAN.md 9.1).
     */
    public function document(string $document): BinaryFileResponse
    {
        $path = storage_path('app/documents/'.$document.'.pdf');

        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }
}
