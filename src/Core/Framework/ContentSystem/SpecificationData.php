<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem;

use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\Log\Package;

/**
 * Bundles the data requirements derived from the entity definition with the
 * placeholder values derived from the request path and query parameters.
 */
#[Package('framework')]
final readonly class SpecificationData
{
    /**
     * @param list<DataRequirement> $dataRequirements
     */
    public function __construct(
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
    ) {
    }
}
