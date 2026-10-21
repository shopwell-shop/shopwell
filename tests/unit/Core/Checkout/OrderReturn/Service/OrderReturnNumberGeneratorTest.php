<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnNumberGenerator;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * The number is quoted to a customer and a support agent, so it has to be short, readable and unique
 * enough in practice. It is not a number range: the prefix and the timestamp are the whole structure.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnNumberGenerator::class)]
class OrderReturnNumberGeneratorTest extends TestCase
{
    public function testTheNumberCarriesThePrefixTheDateAndASuffix(): void
    {
        $generator = new OrderReturnNumberGenerator(new MockClock('2026-10-21 09:30:15'));

        static::assertMatchesRegularExpression('/^RT20261021093015[0-9A-F]{6}$/', $generator->generate());
    }

    public function testTwoNumbersOfTheSameSecondDiffer(): void
    {
        $generator = new OrderReturnNumberGenerator(new MockClock('2026-10-21 09:30:15'));

        static::assertNotSame($generator->generate(), $generator->generate());
    }
}
