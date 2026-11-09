<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Rendering\ContextDelivery;
use Shopwell\Core\Framework\ContentSystem\Rendering\ContextDeliveryIndex;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextDeliveryIndex::class)]
class ContextDeliveryIndexTest extends TestCase
{
    public function testKnownElementWithNoDeliveryIsPresent(): void
    {
        $delivery = new ContextDelivery('root');
        $index = new ContextDeliveryIndex(['root' => $delivery]);

        static::assertTrue($index->has('root'));
        static::assertSame($delivery, $index->deliveryFor('root'));
        static::assertSame([], $index->deliveryFor('root')->context);
        static::assertSame(['root' => $delivery], $index->all());
    }

    public function testMissingElementDoesNotMasqueradeAsAnEmptyDelivery(): void
    {
        $index = new ContextDeliveryIndex(['root' => new ContextDelivery('root')]);
        static::assertFalse($index->has('foreign-element'));
        $this->expectExceptionObject(ContentSystemException::contextDeliveryMissing('foreign-element'));

        $index->deliveryFor('foreign-element');
    }

    public function testAllKeepsSeparateRootsAndNullDeliveredValues(): void
    {
        $first = new ContextDelivery('first', ['product' => null]);
        $second = new ContextDelivery('second', ['title' => 'Welcome']);
        $index = new ContextDeliveryIndex(['first' => $first, 'second' => $second]);

        static::assertSame(['first' => $first, 'second' => $second], $index->all());
        static::assertSame(['product' => null], $index->deliveryFor('first')->context);
        static::assertSame($second, $index->deliveryFor('second'));
    }
}
