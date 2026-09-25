<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class MaxResult extends AggregationResult
{
    public function __construct(
        string $name,
        protected string|float|int|null $max
    ) {
        parent::__construct($name);
    }

    public function getMax(): string|float|int|null
    {
        return $this->max;
    }
}
