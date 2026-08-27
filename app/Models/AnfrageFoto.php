<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anfrage_id', 'pfad', 'thumbnail_pfad', 'beschreibung', 'erstellt_von'])]
class AnfrageFoto extends Model
{
    protected $table = 'anfrage_fotos';

    public function anfrage(): BelongsTo
    {
        return $this->belongsTo(Anfrage::class, 'anfrage_id');
    }
}
