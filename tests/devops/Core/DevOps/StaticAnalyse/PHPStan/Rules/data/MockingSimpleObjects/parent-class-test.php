<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Foo;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Tests\Unit\Administration\AdministrationTest;

class BarTest extends AdministrationTest
{
    public function testFoo(): void
    {
        $this->createMock(OrderEntity::class);
    }
}
