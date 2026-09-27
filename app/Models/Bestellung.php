<?php

namespace App\Models;

use App\Enums\BestellungStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nr', 'lieferant_id', 'titel', 'kategorie', 'projekt_id', 'kunde_id',
    'ersteller_id', 'liefertermin', 'status', 'notizen', 'in_arbeit_am', 'bereit_am', 'vom_lieferanten',
])]
class Bestellung extends Model
{
    use HasFactory;

    protected $table = 'bestellungen';

    protected function casts(): array
    {
        return [
            'status' => BestellungStatus::class,
            'liefertermin' => 'date',
            'in_arbeit_am' => 'datetime',
            'bereit_am' => 'datetime',
            'vom_lieferanten' => 'boolean',
        ];
    }

    public function kategorieLabel(): string
    {
        return match ($this->kategorie) {
            'glas' => 'Glas',
            'aluminium' => 'Aluminium / Zubehör',
            'gemischt' => 'Gemischt',
            'markise' => 'Markisen',
            'sonnensegel' => 'Sonnensegel (Tuch)',
            default => $this->kategorie ?? '–',
        };
    }

    /**
     * Kurzzeile unter dem Status in den Listen: seit wann bestellt / in
     * Arbeit bzw. abholbereit — und ob der Lieferant es selbst gemeldet hat.
     */
    public function meldungKurz(): ?string
    {
        $zeitpunkt = match ($this->status) {
            BestellungStatus::Bestellt => $this->in_arbeit_am,
            BestellungStatus::Bereit => $this->bereit_am,
            default => null,
        };
        if ($zeitpunkt === null) {
            return null;
        }

        return ($this->vom_lieferanten ? 'Lieferant · ' : '').'seit '.$zeitpunkt->timezone('Europe/Berlin')->format('d.m. H:i');
    }

    public function kategoriePillClass(): string
    {
        return match ($this->kategorie) {
            'glas' => 'kat-glas',
            'aluminium' => 'kat-alu',
            default => 'kat-mix',
        };
    }

    public function touren(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'tour_bestellung', 'bestellung_id', 'tour_id');
    }

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class, 'lieferant_id');
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function kunde(): BelongsTo
    {
        return $this->belongsTo(Kunde::class, 'kunde_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ersteller_id');
    }

    public function positionen(): HasMany
    {
        return $this->hasMany(BestellungPosition::class, 'bestellung_id');
    }

    public function wareneingaenge(): HasMany
    {
        return $this->hasMany(Wareneingang::class, 'bestellung_id');
    }
}
