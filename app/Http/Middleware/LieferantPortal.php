<?php

namespace App\Http\Middleware;

use App\Enums\BestellungStatus;
use App\Enums\Rolle;
use App\Models\Bestellung;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lieferanten-Portal (M14): Benutzer mit Rolle «lieferant» bewegen sich
 * ausschließlich in ihren eigenen Bestellungen — jede andere Seite leitet
 * zur Bestellliste um, fremde Bestellungen und interne Entwürfe
 * existieren für sie nicht (404); ab «geprüft» ist die Bestellung sichtbar. Läuft auf der gesamten Auth-Gruppe.
 */
class LieferantPortal
{
    /** @var list<string> Routen, die das Portal ausmachen. */
    private const ERLAUBT = [
        'bestellungen', 'bestellungen.show', 'bestellungen.pdf', 'bestellungen.status',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->role !== Rolle::Lieferant) {
            return $next($request);
        }

        if (! $request->routeIs(...self::ERLAUBT)) {
            return redirect()->route('bestellungen');
        }

        $bestellung = $request->route('bestellung');
        if ($bestellung instanceof Bestellung) {
            abort_if($user->lieferant_id === null
                || $bestellung->lieferant_id !== $user->lieferant_id
                || $bestellung->status === BestellungStatus::Entwurf, 404);
        }

        return $next($request);
    }
}
