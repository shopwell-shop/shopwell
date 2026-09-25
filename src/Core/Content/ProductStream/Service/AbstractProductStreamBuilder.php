<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * Enriches a criteria with a product stream's filters and grouping state.
 */
#[Package('inventory')]
abstract class AbstractProductStreamBuilder
{
    abstract public function enrichCriteria(Criteria $criteria, string $id, Context $context): void;
}
