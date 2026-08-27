<?php

namespace App\Models;

use App\Enums\LagerbewegungTyp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['datum', 'typ', 'artikel_id', 'menge', 'referenz', 'benutzer_id', 'benutzer_name'])]
class Lagerbewegung extends Model
{
    protected $table = 'lagerbewegungen';

    protected function casts(): array
    {
        return [
            'typ' => LagerbewegungTyp::class,
            'datum' => 'datetime',
            'menge' => 'integer',
        ];
    }

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'artikel_id');
    }

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id');
    }
}
