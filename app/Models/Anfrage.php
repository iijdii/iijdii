<?php

namespace App\Models;

use App\Enums\AnfrageStatus;
use App\Enums\Prioritaet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Anfrage extends Model
{
    use HasFactory;

    protected $table = 'anfragen';

    /** Breites Formularobjekt — Massenzuweisung über alles außer id. */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AnfrageStatus::class,
            'details' => 'array',
            'prioritaet' => Prioritaet::class,
            'interessierte_produkte' => 'array',
            'objekt_ist_gleich_kunde' => 'boolean',
            'besuchstermin_datum' => 'date',
            'flaeche_m2' => 'decimal:2',
            'seitenwand_links' => 'boolean',
            'seitenwand_rechts' => 'boolean',
            'schiebesystem' => 'boolean',
            'markise' => 'boolean',
            'led_beleuchtung' => 'boolean',
            'led_laenge_m' => 'decimal:2',
            'sonnensegel' => 'boolean',
            'keil_links' => 'boolean',
            'keil_rechts' => 'boolean',
            'budget_vorhanden' => 'boolean',
            'budget_ca_euro' => 'decimal:2',
            'finanzierung_gewuenscht' => 'boolean',
        ];
    }

    public function kunde(): BelongsTo
    {
        return $this->belongsTo(Kunde::class, 'kunde_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }

    public function besuchsterminMitarbeiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'besuchstermin_mitarbeiter');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(AnfrageFoto::class, 'anfrage_id');
    }

    public function aktivitaeten(): HasMany
    {
        return $this->hasMany(AnfrageAktivitaet::class, 'anfrage_id');
    }

    public function angebot(): HasOne
    {
        return $this->hasOne(Angebot::class, 'anfrage_id');
    }
}
