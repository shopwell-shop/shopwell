<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\DocumentV2\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\DocumentV2\Template\PaginationCounter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(PaginationCounter::class)]
class PaginationCounterTest extends TestCase
{
    public function testIncrementAdvancesTheCounterByOne(): void
    {
        $counter = new PaginationCounter();

        static::assertSame(0, $counter->getCounter());

        $counter->increment();
        $counter->increment();

        static::assertSame(2, $counter->getCounter());
    }
}
