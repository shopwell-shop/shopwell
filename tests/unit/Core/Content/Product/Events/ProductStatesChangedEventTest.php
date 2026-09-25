<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopwell\Core\Content\Product\Events\ProductStatesChangedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStatesChangedEvent::class)]
class ProductStatesChangedEventTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExposesTheUpdatedStatesAndContext(): void
    {
        $context = Context::createDefaultContext();
        $states = [new UpdatedStates('product-id', ['physical'], ['digital'])];

        $event = new ProductStatesChangedEvent($states, $context);

        static::assertSame($states, $event->getUpdatedStates());
        static::assertSame($context, $event->getContext());
    }
}
