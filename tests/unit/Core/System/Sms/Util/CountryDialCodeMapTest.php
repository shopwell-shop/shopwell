<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Util\CountryDialCodeMap;

/**
 * The shipped table is data, and its two invariants are what the resolver relies on: a key is an ISO
 * code in the shape the customer data uses, and a dial code belongs to exactly one country. A table
 * that broke the second one would make the reverse lookup of the resolver silently pick a country,
 * so it is checked here instead of in the resolver.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(CountryDialCodeMap::class)]
class CountryDialCodeMapTest extends TestCase
{
    public function testTheTableIsNotEmpty(): void
    {
        static::assertNotEmpty(CountryDialCodeMap::DIAL_CODES);
    }

    public function testEveryKeyIsAnUpperCaseIsoCode(): void
    {
        foreach (array_keys(CountryDialCodeMap::DIAL_CODES) as $iso) {
            static::assertSame(
                $iso,
                strtoupper($iso),
                'The resolver upper cases the codes of a provider before it merges them, so the shipped table has to be upper case as well.'
            );
            static::assertSame(2, \strlen($iso), \sprintf('"%s" is no two letter ISO code.', $iso));
            static::assertTrue(ctype_alpha($iso), \sprintf('"%s" is no two letter ISO code.', $iso));
        }
    }

    public function testEveryDialCodeIsDigitsOnly(): void
    {
        foreach (CountryDialCodeMap::DIAL_CODES as $iso => $dialCode) {
            static::assertTrue(
                ctype_digit($dialCode),
                \sprintf('The dial code of "%s" is "%s", which is no sequence of digits.', $iso, $dialCode)
            );
            static::assertNotSame('', $dialCode, \sprintf('The dial code of "%s" is empty.', $iso));
        }
    }

    public function testNoDialCodeIsClaimedByTwoCountries(): void
    {
        $seen = [];

        foreach (CountryDialCodeMap::DIAL_CODES as $iso => $dialCode) {
            static::assertArrayNotHasKey(
                $dialCode,
                $seen,
                \sprintf('The dial code "%s" is claimed by "%s" and "%s".', $dialCode, $seen[$dialCode] ?? '', $iso)
            );

            $seen[$dialCode] = $iso;
        }
    }

    public function testTheTableCarriesTheCountriesTheStorefrontAddresses(): void
    {
        static::assertSame('86', CountryDialCodeMap::DIAL_CODES['CN'] ?? null);
        static::assertSame('852', CountryDialCodeMap::DIAL_CODES['HK'] ?? null);
        static::assertSame('1', CountryDialCodeMap::DIAL_CODES['US'] ?? null);
        static::assertSame('49', CountryDialCodeMap::DIAL_CODES['DE'] ?? null);
    }
}
