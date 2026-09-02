<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nr', 'fahrzeug', 'kennzeichen', 'datum', 'fahrer'])]
class Tour extends Model
{
    protected $table = 'touren';

    protected function casts(): array
    {
        return ['datum' => 'date'];
    }

    public function bestellungen(): BelongsToMany
    {
        return $this->belongsToMany(Bestellung::class, 'tour_bestellung', 'tour_id', 'bestellung_id');
    }
}
