<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Document\DocumentGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Document\DocumentGenerator\Counter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Counter::class)]
class CounterTest extends TestCase
{
    public function testCounter(): void
    {
        $counter = new Counter();

        static::assertSame(0, $counter->getCounter());

        $counter->increment();

        static::assertSame(1, $counter->getCounter());

        $counter->increment();
        $counter->increment();
        $counter->increment();

        static::assertSame(4, $counter->getCounter());
    }
}
