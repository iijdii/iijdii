<?php

namespace Tests\Feature;

use App\Models\Abnahmeprotokoll;
use App\Models\Projekt;
use App\Support\Nummern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NummernTest extends TestCase
{
    use RefreshDatabase;

    public function test_abnahme_sequence_starts_at_the_prototype_number(): void
    {
        $this->assertSame('AP-2026-0114', Nummern::abnahme());

        Abnahmeprotokoll::query()->create([
            'nr' => 'AP-2026-0114',
            'projekt_id' => Projekt::factory()->create()->id,
            'art' => 'ohne',
        ]);

        $this->assertSame('AP-2026-0115', Nummern::abnahme());
    }
}
