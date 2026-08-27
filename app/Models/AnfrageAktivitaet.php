<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anfrage_id', 'typ', 'von_user_id', 'details'])]
class AnfrageAktivitaet extends Model
{
    protected $table = 'anfrage_aktivitaeten';

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function anfrage(): BelongsTo
    {
        return $this->belongsTo(Anfrage::class, 'anfrage_id');
    }

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'von_user_id');
    }
}
