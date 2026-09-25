<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopwell\Core\Content\Product\Events\ProductStatesBeforeChangeEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStatesBeforeChangeEvent::class)]
class ProductStatesBeforeChangeEventTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUpdatedStatesCanBeReadAndReplaced(): void
    {
        $context = Context::createDefaultContext();
        $initial = [new UpdatedStates('product-id', ['physical'], ['digital'])];

        $event = new ProductStatesBeforeChangeEvent($initial, $context);

        static::assertSame($initial, $event->getUpdatedStates());
        static::assertSame($context, $event->getContext());

        $replacement = [new UpdatedStates('other-id', ['digital'], ['physical'])];
        $event->setUpdatedStates($replacement);

        static::assertSame($replacement, $event->getUpdatedStates());
    }
}
