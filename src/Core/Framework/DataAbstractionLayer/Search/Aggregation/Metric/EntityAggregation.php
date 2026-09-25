<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class EntityAggregation extends Aggregation
{
    public function __construct(
        string $name,
        string $field,
        protected readonly string $entity
    ) {
        parent::__construct($name, $field);
    }

    public function getEntity(): string
    {
        return $this->entity;
    }
}
