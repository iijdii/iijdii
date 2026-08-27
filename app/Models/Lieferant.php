<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'ansprechpartner', 'email', 'telefon', 'strasse', 'plz', 'stadt'])]
class Lieferant extends Model
{
    use HasFactory;

    protected $table = 'lieferanten';

    public function artikel(): HasMany
    {
        return $this->hasMany(Artikel::class, 'lieferant_id');
    }

    public function bestellungen(): HasMany
    {
        return $this->hasMany(Bestellung::class, 'lieferant_id');
    }
}
