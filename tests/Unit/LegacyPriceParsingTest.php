<?php

use App\Console\Commands\MigrateLegacyData;

/**
 * Legacy stored prices as varchar(50). The production dump contains both
 * well-formed Indonesian formatting ('24.000') and corrupted values with a
 * stray trailing digit ('24.0002', '98.6002').
 *
 * The rule under test: parse what is unambiguous, FLAG what is not. A silently
 * wrong price on a public page is worse than a blank one.
 */
function parsePrice(?string $raw): array
{
    $method = new ReflectionMethod(MigrateLegacyData::class, 'parseLegacyPrice');

    return $method->invoke(new MigrateLegacyData, $raw);
}

it('parses well-formed indonesian thousands separators', function () {
    expect(parsePrice('24.000'))->toBe(['value' => 24000.0, 'flagged' => false]);
    expect(parsePrice('98.600'))->toBe(['value' => 98600.0, 'flagged' => false]);
    expect(parsePrice('1.250.000'))->toBe(['value' => 1250000.0, 'flagged' => false]);
});

it('flags the corrupted values found in the production dump', function () {
    // These are the actual values in layanan_section, legacy id 1.
    expect(parsePrice('24.0002'))->toMatchArray(['flagged' => true]);
    expect(parsePrice('98.6002'))->toMatchArray(['flagged' => true]);
});

it('handles a plain integer string', function () {
    expect(parsePrice('24000'))->toBe(['value' => 24000.0, 'flagged' => false]);
});

it('returns null without flagging for a blank value', function () {
    expect(parsePrice(null))->toBe(['value' => null, 'flagged' => false]);
    expect(parsePrice(''))->toBe(['value' => null, 'flagged' => false]);
});

it('flags a value with no digits at all', function () {
    expect(parsePrice('hubungi kami'))->toBe(['value' => null, 'flagged' => true]);
});
