<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class AvgResult extends AggregationResult
{
    public function __construct(
        string $name,
        protected float $avg
    ) {
        parent::__construct($name);
    }

    public function getAvg(): float
    {
        return $this->avg;
    }
}
