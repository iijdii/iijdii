<?php

namespace App\Http\Controllers;

use App\Enums\Rolle;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Benutzerverwaltung in den Einstellungen (nur Admin): Zugänge anlegen,
 * Name/E-Mail/Rolle/Passwort ändern, sperren und löschen. Der eigene
 * Zugang kann weder gesperrt, gelöscht noch herabgestuft werden, damit
 * immer ein Administrator übrig bleibt.
 */
class BenutzerController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $daten = $request->validateWithBag('benutzer_neu', $this->regeln($request));

        User::query()->create([
            'name' => $daten['name'],
            'email' => $daten['email'],
            'role' => $daten['role'],
            'lieferant_id' => $daten['role'] === Rolle::Lieferant->value ? $daten['lieferant_id'] : null,
            'password' => $daten['password'],
        ]);

        return redirect()->route('einstellungen')->with('toast', 'Benutzer '.$daten['name'].' angelegt');
    }

    public function update(Request $request, User $benutzer): RedirectResponse
    {
        $bag = 'benutzer_'.$benutzer->id;
        $daten = $request->validateWithBag($bag, $this->regeln($request, $benutzer));

        if ($benutzer->is($request->user()) && $daten['role'] !== Rolle::Admin->value) {
            throw ValidationException::withMessages(['role' => 'Die eigene Admin-Rolle kann nicht entzogen werden.'])->errorBag($bag);
        }

        $benutzer->fill([
            'name' => $daten['name'],
            'email' => $daten['email'],
            'role' => $daten['role'],
            'lieferant_id' => $daten['role'] === Rolle::Lieferant->value ? $daten['lieferant_id'] : null,
        ]);
        if (filled($daten['password'] ?? null)) {
            $benutzer->password = $daten['password'];
        }
        $benutzer->save();

        return redirect()->route('einstellungen')->with('toast', 'Benutzer '.$benutzer->name.' gespeichert'
            .(filled($daten['password'] ?? null) ? ' · neues Passwort gesetzt' : ''));
    }

    public function sperre(Request $request, User $benutzer): RedirectResponse
    {
        if ($benutzer->is($request->user())) {
            return redirect()->route('einstellungen')->with('toast', 'Den eigenen Zugang können Sie nicht sperren');
        }

        $benutzer->update(['aktiv' => $benutzer->istGesperrt()]);

        return redirect()->route('einstellungen')->with('toast', 'Benutzer '.$benutzer->name.' '.($benutzer->istGesperrt() ? 'gesperrt' : 'entsperrt'));
    }

    public function loesche(Request $request, User $benutzer): RedirectResponse
    {
        if ($benutzer->is($request->user())) {
            return redirect()->route('einstellungen')->with('toast', 'Den eigenen Zugang können Sie nicht löschen');
        }

        try {
            $benutzer->delete();
        } catch (QueryException) {
            return redirect()->route('einstellungen')
                ->with('toast', $benutzer->name.' hat bereits Einträge (Bestellungen, Aufmaß …) — bitte sperren statt löschen');
        }

        return redirect()->route('einstellungen')->with('toast', 'Benutzer '.$benutzer->name.' gelöscht');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function regeln(Request $request, ?User $benutzer = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($benutzer?->id)],
            'role' => ['required', Rule::enum(Rolle::class)],
            'lieferant_id' => [Rule::requiredIf($request->input('role') === Rolle::Lieferant->value), 'nullable', 'exists:lieferanten,id'],
            'password' => [$benutzer ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }
}
