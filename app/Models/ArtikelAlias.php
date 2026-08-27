<?php

namespace App\Models;

use App\Support\AliasNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['artikel_id', 'alias'])]
class ArtikelAlias extends Model
{
    protected $table = 'artikel_aliase';

    protected static function booted(): void
    {
        static::saving(function (self $alias) {
            $alias->alias_normalized = AliasNormalizer::normalize($alias->alias);
        });
    }

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'artikel_id');
    }
}
