<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\DataAbstractionLayer;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractProductStreamUpdater extends EntityIndexer
{
    /**
     * @param array<string> $ids
     */
    abstract public function updateProducts(array $ids, Context $context): void;
}
