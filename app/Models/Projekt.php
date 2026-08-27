<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nr', 'titel', 'kunde_id', 'angebot_id', 'objekt_strasse',
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
            'termin_von' => 'date',
            'termin_bis' => 'date',
            'konfiguration' => 'array',
            'aufmass' => 'array',
        ];
    }

    public function kunde(): BelongsTo
    {
        return $this->belongsTo(Kunde::class, 'kunde_id');
    }

    public function angebot(): BelongsTo
    {
        return $this->belongsTo(Angebot::class, 'angebot_id');
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
}
