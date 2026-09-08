<?php

namespace App\Models;

use App\Enums\ProjektStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nr', 'titel', 'kunde_id', 'angebot_id', 'anfrage_id', 'projektleiter_id', 'objekt_strasse',
    'objekt_hausnummer', 'objekt_plz', 'objekt_stadt', 'status',
    'termin_von', 'termin_bis', 'konfiguration', 'aufmass',
])]
class Projekt extends Model
{
    use HasFactory;

    protected $table = 'projekte';

    protected function casts(): array
    {
        return [
            'status' => ProjektStatus::class,
            'termin_von' => 'date',
            'termin_bis' => 'date',
            'konfiguration' => 'array',
            'aufmass' => 'array',
        ];
    }

    public function projektleiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'projektleiter_id');
    }

    public function kunde(): BelongsTo
    {
        return $this->belongsTo(Kunde::class, 'kunde_id');
    }

    public function angebot(): BelongsTo
    {
        return $this->belongsTo(Angebot::class, 'angebot_id');
    }

    public function anfrage(): BelongsTo
    {
        return $this->belongsTo(Anfrage::class, 'anfrage_id');
    }

    public function bestellungen(): HasMany
    {
        return $this->hasMany(Bestellung::class, 'projekt_id');
    }

    public function reservierungen(): HasMany
    {
        return $this->hasMany(Reservierung::class, 'projekt_id');
    }

    public function dokumente(): HasMany
    {
        return $this->hasMany(Dokument::class, 'projekt_id');
    }

    public function abnahmeprotokolle(): HasMany
    {
        return $this->hasMany(Abnahmeprotokoll::class, 'projekt_id');
    }

    public function montageAufgaben(): HasMany
    {
        return $this->hasMany(MontageAufgabe::class, 'projekt_id');
    }

    public function montageNotizen(): HasMany
    {
        return $this->hasMany(MontageNotiz::class, 'projekt_id');
    }

    public function montageZusatzmaterial(): HasMany
    {
        return $this->hasMany(MontageZusatzmaterial::class, 'projekt_id');
    }

    public function aktivitaeten(): HasMany
    {
        return $this->hasMany(ProjektAktivitaet::class, 'projekt_id');
    }

    /**
     * Fünf-Stufen-Fortschritt der Detailkopfzeile (Aufmaß → Angebot →
     * Produktion → Lieferung → Montage), abgeleitet aus dem Status —
     * im Prototyp waren die Stufen hartkodiert.
     */
    public function stepper(): array
    {
        $stufe = match ($this->status) {
            ProjektStatus::InPlanung => $this->angebot_id ? 2 : 1,
            ProjektStatus::InMontageplanung => 4,
            ProjektStatus::InMontage => 5,
            ProjektStatus::Abgeschlossen => 6,
        };

        $daten = [
            ['Aufmaß', null],
            ['Angebot', $this->angebot?->datum],
            ['Produktion', null],
            ['Lieferung', null],
            ['Montage', $this->termin_von],
        ];

        return collect($daten)->map(fn ($s, $i) => [
            'label' => $s[0],
            'datum' => $s[1] ? $s[1]->format('d.m.Y') : '–',
            'cls' => $i + 1 < $stufe ? 'done' : ($i + 1 === $stufe ? 'now' : ''),
        ])->all();
    }
}
