<?php

namespace Tests\Feature;

use App\Models\Abnahmeprotokoll;
use App\Models\Projekt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AbnahmeprotokollImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function protokoll(array $attribute = []): Abnahmeprotokoll
    {
        return Abnahmeprotokoll::query()->create(array_merge([
            'nr' => 'AP-2026-0114',
            'projekt_id' => Projekt::factory()->create()->id,
            'art' => 'ohne',
            'ort' => 'Berlin',
            'datum' => '2026-07-19',
        ], $attribute));
    }

    public function test_unsigned_protocol_can_be_updated(): void
    {
        $protokoll = $this->protokoll();

        $protokoll->update(['ort' => 'Potsdam']);

        $this->assertSame('Potsdam', $protokoll->fresh()->ort);
    }

    public function test_signed_protocol_is_immutable(): void
    {
        $protokoll = $this->protokoll(['abgeschlossen_am' => now()]);

        $this->expectException(LogicException::class);

        $protokoll->fresh()->update(['ort' => 'Potsdam']);
    }
}
