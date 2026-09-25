<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractIsNewDetector
{
    abstract public function getDecorated(): AbstractIsNewDetector;

    abstract public function isNew(Entity $product, SalesChannelContext $context): bool;
}
