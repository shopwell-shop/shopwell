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
 * Each test collects the entries that break its invariant and answers with the list, so a broken row
 * is named in the failure message instead of stopping at the first one.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(CountryDialCodeMap::class)]
class CountryDialCodeMapTest extends TestCase
{
    /**
     * The countries the storefront addresses, with the dial code the routing has to answer for them.
     */
    private const EXPECTED_COUNTRIES = [
        'CN' => '86',
        'HK' => '852',
        'US' => '1',
        'DE' => '49',
    ];

    public function testTheTableIsNotEmpty(): void
    {
        static::assertGreaterThan(
            0,
            \count(self::shippedTable()),
            'An empty table would route every number to GLOBAL, which is the same as having no table at all.'
        );
    }

    public function testEveryKeyIsAnUpperCaseIsoCode(): void
    {
        $invalid = [];

        foreach (array_keys(self::shippedTable()) as $iso) {
            if ($iso !== strtoupper($iso) || \strlen($iso) !== 2 || !ctype_alpha($iso)) {
                $invalid[] = $iso;
            }
        }

        static::assertSame(
            [],
            $invalid,
            'The resolver upper cases the codes of a provider before it merges them, so the shipped table has to carry upper case two letter ISO codes as well.'
        );
    }

    public function testEveryDialCodeIsDigitsOnly(): void
    {
        $invalid = [];

        foreach (self::shippedTable() as $iso => $dialCode) {
            if ($dialCode === '' || !ctype_digit($dialCode)) {
                $invalid[] = \sprintf('%s => "%s"', $iso, $dialCode);
            }
        }

        static::assertSame(
            [],
            $invalid,
            'The dial code is concatenated to the number without a separator, so it has to be digits only and may not be empty.'
        );
    }

    public function testNoDialCodeIsClaimedByTwoCountries(): void
    {
        $owner = [];
        $collisions = [];

        foreach (self::shippedTable() as $iso => $dialCode) {
            if (isset($owner[$dialCode])) {
                $collisions[] = \sprintf('"%s" is claimed by "%s" and "%s"', $dialCode, $owner[$dialCode], $iso);

                continue;
            }

            $owner[$dialCode] = $iso;
        }

        static::assertSame(
            [],
            $collisions,
            'The reverse lookup of the resolver answers one country per dial code, so a second owner would be silently unreachable.'
        );
    }

    public function testTheTableCarriesTheCountriesTheStorefrontAddresses(): void
    {
        $table = self::shippedTable();
        $unexpected = [];

        foreach (self::EXPECTED_COUNTRIES as $iso => $dialCode) {
            $shipped = $table[$iso] ?? null;
            if ($shipped !== $dialCode) {
                $unexpected[] = \sprintf('%s => "%s" (expected "%s")', $iso, (string) $shipped, $dialCode);
            }
        }

        static::assertSame([], $unexpected, 'These countries come up in the addresses the storefront ships with.');
    }

    /**
     * The table as the resolver consumes it: an ordinary map from ISO code to dial code. Reading the
     * constant directly would let the static analysis fold every assertion of this test into a
     * comparison of two literals, which would always hold and prove nothing about the data.
     *
     * @return array<string, string>
     */
    private static function shippedTable(): array
    {
        return CountryDialCodeMap::DIAL_CODES;
    }
}
