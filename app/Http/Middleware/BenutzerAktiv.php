<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gesperrte Benutzer (users.aktiv = false) werden sofort abgemeldet —
 * auch mit noch offener Sitzung auf einem anderen Gerät.
 */
class BenutzerAktiv
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->istGesperrt()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Dieser Zugang ist gesperrt.']);
        }

        return $next($request);
    }
}
