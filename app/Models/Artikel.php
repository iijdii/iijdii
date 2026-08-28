<?php

namespace App\Models;

use App\Enums\ArtikelKategorie;
use App\Enums\Einheit;
use App\Support\AliasNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'art_nr', 'name', 'kategorie', 'bild', 'einheit', 'lagerort',
    'bestand', 'min_bestand', 'ek_preis', 'lieferant_id',
])]
class Artikel extends Model
{
    use HasFactory;

    protected $table = 'artikel';

    protected function casts(): array
    {
        return [
            'kategorie' => ArtikelKategorie::class,
            'einheit' => Einheit::class,
            'bestand' => 'integer',
            'min_bestand' => 'integer',
            'ek_preis' => 'decimal:2',
        ];
    }

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class, 'lieferant_id');
    }

    public function aliase(): HasMany
    {
        return $this->hasMany(ArtikelAlias::class, 'artikel_id');
    }

    public function reservierungen(): HasMany
    {
        return $this->hasMany(Reservierung::class, 'artikel_id');
    }

    public function bewegungen(): HasMany
    {
        return $this->hasMany(Lagerbewegung::class, 'artikel_id');
    }

    /** Reservierte Menge — nutzt withSum('reservierungen','menge'), wenn geladen. */
    public function reserviert(): int
    {
        $summe = $this->attributes['reservierungen_sum_menge']
            ?? $this->reservierungen()->sum('menge');

        return (int) $summe;
    }

    public function verfuegbar(): int
    {
        return $this->bestand - $this->reserviert();
    }

    /** 'leer' | 'niedrig' | 'ok' — Schwellen auf dem verfügbaren Bestand. */
    public function bestandsstatus(): string
    {
        $verf = $this->verfuegbar();

        if ($verf <= 0) {
            return 'leer';
        }

        return $verf < $this->min_bestand ? 'niedrig' : 'ok';
    }

    /** Füllbalken: 100 % beim doppelten Mindestbestand, min. 3 % sichtbar. */
    public function fuellstandProzent(): int
    {
        return (int) min(100, max(3, round($this->bestand / max($this->min_bestand * 2, 1) * 100)));
    }

    public function lagerwert(): float
    {
        return $this->bestand * (float) $this->ek_preis;
    }

    /**
     * Freitext (Bestellzeile, Konfigurator-Ausgabe) auf einen Artikel
     * auflösen: erst exakte Art-Nr., dann Name, dann Alias — jeweils
     * über die normalisierte Form.
     */
    public static function findeNachName(string $freitext): ?self
    {
        $norm = AliasNormalizer::normalize($freitext);

        foreach (static::query()->get(['id', 'art_nr', 'name']) as $artikel) {
            if (AliasNormalizer::normalize($artikel->art_nr) === $norm
                || AliasNormalizer::normalize($artikel->name) === $norm) {
                return static::find($artikel->id);
            }
        }

        $alias = ArtikelAlias::query()->where('alias_normalized', $norm)->first();

        return $alias?->artikel;
    }
}
