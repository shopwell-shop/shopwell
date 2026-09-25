<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class MinResult extends AggregationResult
{
    public function __construct(
        string $name,
        protected float|int|string|null $min
    ) {
        parent::__construct($name);
    }

    public function getMin(): float|int|string|null
    {
        return $this->min;
    }
}
