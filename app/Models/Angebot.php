<?php

namespace App\Models;

use App\Enums\AngebotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nr', 'kunde_id', 'anfrage_id', 'titel', 'status', 'datum', 'summe', 'konfiguration',
    'gueltig_bis', 'rabatt_prozent', 'preise', 'rabatte', 'freie_positionen', 'ausgeblendet',
    'accept_token', 'angenommen_am', 'angenommen_ip', 'kunden_kommentar'])]
class Angebot extends Model
{
    use HasFactory;

    protected $table = 'angebote';

    protected function casts(): array
    {
        return [
            'status' => AngebotStatus::class,
            'datum' => 'date',
            'summe' => 'decimal:2',
            'konfiguration' => 'array',
            'gueltig_bis' => 'date',
            'rabatt_prozent' => 'decimal:2',
            'preise' => 'array',
            'rabatte' => 'array',
            'freie_positionen' => 'array',
            'ausgeblendet' => 'array',
            'angenommen_am' => 'datetime',
        ];
    }

    /**
     * Permanenter Token der Online-Annahme (Spez. v3.2: einmal erzeugt,
     * bleibt konstant). Bestandsangebote erhalten ihn nachträglich.
     */
    public function stelleAnnahmeTokenSicher(): string
    {
        if (! $this->accept_token) {
            $this->forceFill(['accept_token' => bin2hex(random_bytes(16))])->saveQuietly();
        }

        return $this->accept_token;
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
