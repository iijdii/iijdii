<?php

namespace App\Models;

use App\Enums\Rolle;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'lieferant_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Rolle::class,
        ];
    }

    public function hasRole(Rolle ...$rollen): bool
    {
        return in_array($this->role, $rollen, true);
    }

    /** Portal-Zugehörigkeit: Rolle «lieferant» sieht nur diesen Lieferanten. */
    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class);
    }

    public function istLieferant(): bool
    {
        return $this->role === Rolle::Lieferant;
    }
}
