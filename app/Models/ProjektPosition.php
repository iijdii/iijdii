<?php

namespace App\Models;

use App\Enums\ProjektProdukt;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['projekt_id', 'pos', 'gruppe', 'produkt', 'felder', 'phase', 'endmasse', 'endmasse_von', 'endmasse_am'])]
class ProjektPosition extends Model
{
    protected $table = 'projekt_positionen';

    protected function casts(): array
    {
        return [
            'produkt' => ProjektProdukt::class,
            'felder' => 'array',
            'endmasse' => 'array',
            'endmasse_am' => 'datetime',
        ];
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function endmasseVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endmasse_von');
    }
}
