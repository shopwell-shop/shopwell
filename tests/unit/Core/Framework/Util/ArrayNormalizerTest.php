<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\ArrayNormalizer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ArrayNormalizer::class)]
class ArrayNormalizerTest extends TestCase
{
    /**
     * @param array<string, mixed> $nested
     * @param array<string, string> $flattened
     */
    #[DataProvider('provideTestData')]
    public function testFlattening(array $nested, array $flattened): void
    {
        static::assertSame($flattened, ArrayNormalizer::flatten($nested));
    }

    /**
     * @param array<string, mixed> $nested
     * @param array<string, string> $flattened
     */
    #[DataProvider('provideTestData')]
    public function testExpanding(array $nested, array $flattened): void
    {
        static::assertSame($nested, ArrayNormalizer::expand($flattened));
    }

    /**
     * @return list<array{array<string, mixed>, array<string, string>}>
     */
    public static function provideTestData(): array
    {
        return [
            [
                [ // nested
                    'name' => 'Foo Bar',
                    'billingAddress' => [
                        'street' => 'Foostreet',
                    ],
                ],
                [ // flattened
                    'name' => 'Foo Bar',
                    'billingAddress.street' => 'Foostreet',
                ],
            ],
        ];
    }
}
