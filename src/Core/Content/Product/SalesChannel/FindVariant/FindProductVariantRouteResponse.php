<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\FindVariant;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<FoundCombination>
 */
#[Package('inventory')]
class FindProductVariantRouteResponse extends StoreApiResponse
{
    public function getFoundCombination(): FoundCombination
    {
        return $this->object;
    }
}
