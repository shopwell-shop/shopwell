<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnEvents;
use Shopwell\Core\Framework\Log\Package;

/**
 * The names are the contract with every plugin that subscribes to them, so they are spelled out here
 * and may not change without a deprecation.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnEvents::class)]
class OrderReturnEventsTest extends TestCase
{
    public function testTheEventNamesAreTheDocumentedContract(): void
    {
        static::assertSame('order_return.created', $this->nameOf('RETURN_CREATED_EVENT'));
        static::assertSame('order_return.completed', $this->nameOf('RETURN_COMPLETED_EVENT'));
        static::assertSame('order_return.cancelled', $this->nameOf('RETURN_CANCELLED_EVENT'));
    }

    public function testTheEventNamesShareTheEntityNameAsPrefix(): void
    {
        foreach (['RETURN_CREATED_EVENT', 'RETURN_COMPLETED_EVENT', 'RETURN_CANCELLED_EVENT'] as $constant) {
            static::assertStringStartsWith('order_return.', $this->nameOf($constant));
        }
    }

    /**
     * Read through a variable so the assertion is about the published name and not about the type of
     * the constant it is written with.
     */
    private function nameOf(string $constant): string
    {
        $name = \constant(OrderReturnEvents::class . '::' . $constant);

        static::assertIsString($name);

        return $name;
    }
}
