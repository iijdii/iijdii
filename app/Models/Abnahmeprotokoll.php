<?php

namespace App\Models;

use App\Enums\AbnahmeArt;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'nr', 'projekt_id', 'art', 'checkliste', 'ort', 'datum',
    'unterschrift_auftraggeber_pfad', 'unterschrift_monteur_pfad',
    'abgeschlossen_am', 'abgeschlossen_von',
])]
class Abnahmeprotokoll extends Model
{
    protected $table = 'abnahmeprotokolle';

    protected function casts(): array
    {
        return [
            'art' => AbnahmeArt::class,
            'checkliste' => 'array',
            'datum' => 'date',
            'abgeschlossen_am' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Ein unterzeichnetes Protokoll ist unveränderlich.
        static::updating(function (self $protokoll) {
            if ($protokoll->getOriginal('abgeschlossen_am') !== null) {
                throw new LogicException(
                    'Abnahmeprotokoll '.$protokoll->nr.' ist unterzeichnet und unveränderlich.'
                );
            }
        });
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function maengel(): HasMany
    {
        return $this->hasMany(AbnahmeMangel::class, 'abnahmeprotokoll_id');
    }
}
