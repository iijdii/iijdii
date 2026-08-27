<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bestellung_id', 'datum', 'benutzer_id', 'lieferschein_nr'])]
class Wareneingang extends Model
{
    protected $table = 'wareneingaenge';

    protected function casts(): array
    {
        return ['datum' => 'date'];
    }

    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(Bestellung::class, 'bestellung_id');
    }

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id');
    }

    public function positionen(): HasMany
    {
        return $this->hasMany(WareneingangPosition::class, 'wareneingang_id');
    }
}
