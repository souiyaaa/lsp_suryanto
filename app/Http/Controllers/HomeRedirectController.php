<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeRedirectController extends Controller
{
    /** Breeze sends everyone to /dashboard after login; this sends each role to its home page. */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $user->isApproved()) {
            return redirect()->route('status');
        }

        return redirect()->route('rentals.index');
    }
}
