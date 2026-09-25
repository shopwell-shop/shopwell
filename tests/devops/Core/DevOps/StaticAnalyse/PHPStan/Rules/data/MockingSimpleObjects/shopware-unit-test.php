<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Foo;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class BarTest extends TestCase
{
    public function testFoo(): void
    {
        // not allowed
        $this->createMock(OrderEntity::class);

        // allowed
        $this->createMock(EntitySearchResult::class);
    }
}
