<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['projekt_id', 'typ', 'dateiname', 'pfad', 'groesse', 'datum', 'badge'])]
class Dokument extends Model
{
    protected $table = 'dokumente';

    protected function casts(): array
    {
        return ['datum' => 'date'];
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }
}
