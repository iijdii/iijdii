<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wareneingang_id', 'artikel_id', 'bestellung_position_id', 'menge'])]
class WareneingangPosition extends Model
{
    protected $table = 'wareneingang_positionen';

    protected function casts(): array
    {
        return ['menge' => 'decimal:2'];
    }

    public function wareneingang(): BelongsTo
    {
        return $this->belongsTo(Wareneingang::class, 'wareneingang_id');
    }

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'artikel_id');
    }

    public function bestellungPosition(): BelongsTo
    {
        return $this->belongsTo(BestellungPosition::class, 'bestellung_position_id');
    }
}
