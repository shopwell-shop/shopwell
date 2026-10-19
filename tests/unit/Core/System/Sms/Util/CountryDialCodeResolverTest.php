<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Util\CountryDialCodeMap;
use Shopwell\Core\System\Sms\Util\CountryDialCodeProviderInterface;
use Shopwell\Core\System\Sms\Util\CountryDialCodeResolver;
use Shopwell\Core\System\Sms\Util\ShippedCountryDialCodeProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CountryDialCodeResolver::class)]
#[CoversClass(ShippedCountryDialCodeProvider::class)]
#[CoversClass(CountryDialCodeMap::class)]
class CountryDialCodeResolverTest extends TestCase
{
    public function testItAnswersBothDirectionsOfTheShippedData(): void
    {
        $resolver = $this->createResolver();

        static::assertSame('CN', $resolver->isoOfDialCode('86'));
        static::assertSame('86', $resolver->dialCodeOfIso('CN'));
        static::assertSame('852', $resolver->dialCodeOfIso('hk'));
    }

    public function testAnUnknownCodeIsNoError(): void
    {
        $resolver = $this->createResolver();

        static::assertNull($resolver->isoOfDialCode('998'));
        static::assertNull($resolver->dialCodeOfIso('UZ'));
    }

    public function testTheLongestPrefixWins(): void
    {
        $resolver = $this->createResolver();

        // 852 is Hong Kong and not China plus two digits of a longer number
        static::assertSame('852', $resolver->matchPrefix('85212345678'));
        static::assertSame('86', $resolver->matchPrefix('8613800000000'));
        static::assertSame('1', $resolver->matchPrefix('14155552671'));
        static::assertNull($resolver->matchPrefix('998901234567'));
    }

    public function testALaterProviderOverridesAnEarlierOne(): void
    {
        $resolver = new CountryDialCodeResolver([
            new ShippedCountryDialCodeProvider(),
            $this->providerOf(['CN' => '87', 'UZ' => '998']),
        ]);

        static::assertSame('87', $resolver->dialCodeOfIso('CN'));
        static::assertSame('998', $resolver->matchPrefix('998901234567'));
        static::assertSame('UZ', $resolver->isoOfDialCode('998'));
    }

    public function testItDoesNotSplitTheNumberItself(): void
    {
        // a single digit is no dial code of the shipped data, and the resolver does not invent one
        static::assertNull($this->createResolver()->matchPrefix('8'));
    }

    private function createResolver(): CountryDialCodeResolver
    {
        return new CountryDialCodeResolver([new ShippedCountryDialCodeProvider()]);
    }

    /**
     * @param array<string, string> $dialCodes
     */
    private function providerOf(array $dialCodes): CountryDialCodeProviderInterface
    {
        return new class($dialCodes) implements CountryDialCodeProviderInterface {
            /**
             * @param array<string, string> $dialCodes
             */
            public function __construct(private readonly array $dialCodes)
            {
            }

            public function getDialCodes(): array
            {
                return $this->dialCodes;
            }
        };
    }
}
