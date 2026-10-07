<?php

namespace Tests\Unit;

use App\Console\Commands\MigrateLegacyData;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Legacy stored prices as varchar(50). The production dump contains both
 * well-formed Indonesian formatting ('24.000') and corrupted values with a
 * stray trailing digit ('24.0002', '98.6002').
 *
 * The rule under test: parse what is unambiguous, FLAG what is not. A silently
 * wrong price on a public page is worse than a blank one.
 */
class LegacyPriceParsingTest extends TestCase
{
    public function test_it_parses_well_formed_indonesian_thousands_separators(): void
    {
        $this->assertSame(['value' => 24000.0, 'flagged' => false], $this->parsePrice('24.000'));
        $this->assertSame(['value' => 98600.0, 'flagged' => false], $this->parsePrice('98.600'));
        $this->assertSame(['value' => 1250000.0, 'flagged' => false], $this->parsePrice('1.250.000'));
    }

    public function test_it_flags_the_corrupted_values_found_in_the_production_dump(): void
    {
        // These are the actual values in layanan_section, legacy id 1.
        $this->assertTrue($this->parsePrice('24.0002')['flagged']);
        $this->assertTrue($this->parsePrice('98.6002')['flagged']);
    }

    public function test_it_handles_a_plain_integer_string(): void
    {
        $this->assertSame(['value' => 24000.0, 'flagged' => false], $this->parsePrice('24000'));
    }

    public function test_it_returns_null_without_flagging_for_a_blank_value(): void
    {
        $this->assertSame(['value' => null, 'flagged' => false], $this->parsePrice(null));
        $this->assertSame(['value' => null, 'flagged' => false], $this->parsePrice(''));
    }

    public function test_it_flags_a_value_with_no_digits_at_all(): void
    {
        $this->assertSame(['value' => null, 'flagged' => true], $this->parsePrice('hubungi kami'));
    }

    /**
     * parseLegacyPrice is private: it is an implementation detail of the
     * migration command, not an API, but its behaviour on the real dump values
     * is worth pinning down.
     *
     * @return array{value: float|null, flagged: bool}
     */
    private function parsePrice(?string $raw): array
    {
        return (new ReflectionMethod(MigrateLegacyData::class, 'parseLegacyPrice'))
            ->invoke(new MigrateLegacyData, $raw);
    }
}
