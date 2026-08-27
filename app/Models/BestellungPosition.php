<?php

namespace App\Models;

use App\Enums\BestellungPositionTyp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bestellung_id', 'typ', 'pos', 'bezeichnung', 'artikel_id',
    'menge', 'einheit', 'breite_mm', 'hoehe_mm', 'details', 'eingelagert',
])]
class BestellungPosition extends Model
{
    protected $table = 'bestellung_positionen';

    protected function casts(): array
    {
        return [
            'typ' => BestellungPositionTyp::class,
            'menge' => 'decimal:2',
            'details' => 'array',
            'eingelagert' => 'boolean',
        ];
    }

    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(Bestellung::class, 'bestellung_id');
    }

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'artikel_id');
    }
}
