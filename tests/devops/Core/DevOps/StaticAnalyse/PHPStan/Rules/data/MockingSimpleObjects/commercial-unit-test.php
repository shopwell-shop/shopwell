<?php

declare(strict_types=1);

namespace Shopwell\Commercial\Tests\Unit\Foo;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

class BarTest extends TestCase
{
    public function testFoo(): void
    {
        // not allowed
        $this->createMock(OrderEntity::class);

        // allowed
        $this->createMock(SalesChannelContext::class);
    }
}
