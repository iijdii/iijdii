<?php

namespace App\Models;

use App\Enums\BestellungStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nr', 'lieferant_id', 'titel', 'kategorie', 'projekt_id', 'kunde_id',
    'ersteller_id', 'liefertermin', 'status', 'notizen',
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
        ];
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
