<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nr', 'kunde_id', 'anfrage_id', 'titel', 'status', 'datum', 'summe', 'konfiguration'])]
class Angebot extends Model
{
    use HasFactory;

    protected $table = 'angebote';

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\AngebotStatus::class,
            'datum' => 'date',
            'summe' => 'decimal:2',
            'konfiguration' => 'array',
        ];
    }

    public function kunde(): BelongsTo
    {
        return $this->belongsTo(Kunde::class, 'kunde_id');
    }

    public function anfrage(): BelongsTo
    {
        return $this->belongsTo(Anfrage::class, 'anfrage_id');
    }

    public function projekt(): HasOne
    {
        return $this->hasOne(Projekt::class, 'angebot_id');
    }
}
