<?php

namespace App\Http\Middleware;

use App\Support\Licensing\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate for a module: `->middleware('module:competitions')`. A module the club hasn't licensed
 * doesn't exist for members (404); a lapsed one can still be read but not changed (403).
 */
class EnsureModule
{
    public function __construct(private readonly Modules $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($this->modules->enabled($module), 404);

        abort_if(
            ! $request->isMethodSafe() && ! $this->modules->writable($module),
            403,
            'This part of Bowls Buddy is read-only until the club renews its licence.',
        );

        return $next($request);
    }
}
