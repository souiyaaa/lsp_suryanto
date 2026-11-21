<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        // Pending and rejected users may log in, but only see their status page (FR-04).
        if (! $request->user()?->isApproved()) {
            return redirect()->route('status');
        }

        return $next($request);
    }
}
