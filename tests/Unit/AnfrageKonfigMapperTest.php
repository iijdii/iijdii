<?php

namespace Tests\Unit;

use App\Support\AnfrageKonfigMapper;
use PHPUnit\Framework\TestCase;

class AnfrageKonfigMapperTest extends TestCase
{
    public function test_richtung_normalization(): void
    {
        $this->assertSame('left', AnfrageKonfigMapper::richtung('Nach links'));
        $this->assertSame('right', AnfrageKonfigMapper::richtung('Nach rechts'));
        $this->assertSame('center', AnfrageKonfigMapper::richtung('Mittig'));
        $this->assertSame('center', AnfrageKonfigMapper::richtung('beidseitig'));
        $this->assertNull(AnfrageKonfigMapper::richtung('quer'));
        $this->assertNull(AnfrageKonfigMapper::richtung(null));
    }
}
