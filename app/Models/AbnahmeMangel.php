<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['abnahmeprotokoll_id', 'text', 'frist'])]
class AbnahmeMangel extends Model
{
    protected $table = 'abnahme_maengel';

    protected function casts(): array
    {
        return ['frist' => 'date'];
    }

    public function abnahmeprotokoll(): BelongsTo
    {
        return $this->belongsTo(Abnahmeprotokoll::class, 'abnahmeprotokoll_id');
    }
}
