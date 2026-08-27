<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'projekt_id', 'datum', 'titel', 'beschreibung', 'sortierung',
    'erstellt_von', 'erledigt_von', 'erledigt_am',
])]
class MontageAufgabe extends Model
{
    protected $table = 'montage_aufgaben';

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'erledigt_am' => 'datetime',
        ];
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}
