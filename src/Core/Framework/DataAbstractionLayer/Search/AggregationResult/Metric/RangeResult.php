<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class RangeResult extends AggregationResult
{
    /**
     * @param array<string, int> $ranges
     */
    public function __construct(
        string $name,
        protected array $ranges
    ) {
        parent::__construct($name);
    }

    /**
     * @return array<string, int>
     */
    public function getRanges(): array
    {
        return $this->ranges;
    }
}
