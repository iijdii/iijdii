<?php

namespace Tests\Unit;

use App\Support\AliasNormalizer;
use PHPUnit\Framework\TestCase;

class AliasNormalizerTest extends TestCase
{
    public function test_lowercases(): void
    {
        $this->assertSame('pfosten', AliasNormalizer::normalize('PFOSTEN'));
    }

    public function test_replaces_multiplication_sign_with_x(): void
    {
        $this->assertSame('pfosten 110x110', AliasNormalizer::normalize('Pfosten 110×110'));
    }

    public function test_collapses_whitespace_and_trims(): void
    {
        $this->assertSame('vsg 8 mm klar', AliasNormalizer::normalize("  VSG   8\tmm  klar "));
    }

    public function test_matches_prototype_norm_examples(): void
    {
        // Identische Normalform → Alias-Treffer wie im Prototyp.
        $this->assertSame(
            AliasNormalizer::normalize('Pfosten 90×90'),
            AliasNormalizer::normalize('pfosten 90x90'),
        );
    }
}
