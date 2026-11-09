<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Detail;

use Shopwell\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
final readonly class ProductConfiguratorProductData
{
    /**
     * @param array<string>|null $optionIds
     */
    public function __construct(
        public string $id,
        public ?string $parentId,
        public ?array $optionIds,
        public ?VariantListingConfig $variantListingConfig,
    ) {
    }
}
