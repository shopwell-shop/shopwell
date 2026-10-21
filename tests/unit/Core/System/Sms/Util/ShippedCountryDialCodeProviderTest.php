<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Util\CountryDialCodeMap;
use Shopwell\Core\System\Sms\Util\CountryDialCodeProviderInterface;
use Shopwell\Core\System\Sms\Util\ShippedCountryDialCodeProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShippedCountryDialCodeProvider::class)]
class ShippedCountryDialCodeProviderTest extends TestCase
{
    public function testItIsAProviderTheResolverAccepts(): void
    {
        static::assertInstanceOf(CountryDialCodeProviderInterface::class, new ShippedCountryDialCodeProvider());
    }

    public function testItAnswersTheShippedTable(): void
    {
        static::assertSame(
            CountryDialCodeMap::DIAL_CODES,
            (new ShippedCountryDialCodeProvider())->getDialCodes(),
            'The provider is the adapter of the table, it must not copy, filter or reorder it.'
        );
    }

    public function testItAnswersTheSameDataOnEveryCall(): void
    {
        $provider = new ShippedCountryDialCodeProvider();

        static::assertSame($provider->getDialCodes(), $provider->getDialCodes());
    }
}
