<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kunden_nr', 'typ', 'anzeigename', 'vorname', 'nachname', 'firma',
    'ansprechpartner', 'email', 'telefon', 'strasse', 'hausnummer',
    'plz', 'stadt', 'region', 'quelle', 'tags', 'notizen', 'status',
])]
class Kunde extends Model
{
    use HasFactory;

    protected $table = 'kunden';

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function anfragen(): HasMany
    {
        return $this->hasMany(Anfrage::class, 'kunde_id');
    }

    public function angebote(): HasMany
    {
        return $this->hasMany(Angebot::class, 'kunde_id');
    }

    public function projekte(): HasMany
    {
        return $this->hasMany(Projekt::class, 'kunde_id');
    }
}
