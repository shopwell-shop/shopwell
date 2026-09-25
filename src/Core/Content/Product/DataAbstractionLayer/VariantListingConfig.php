<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\DataAbstractionLayer;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('framework')]
class VariantListingConfig extends Struct
{
    /**
     * @param list<array{id: string, representation?: string, expressionForListings?: bool}>|null $configuratorGroupConfig
     */
    public function __construct(
        protected ?bool $displayParent,
        protected ?string $mainVariantId,
        protected ?array $configuratorGroupConfig
    ) {
    }

    public function getDisplayParent(): ?bool
    {
        return $this->displayParent;
    }

    public function getMainVariantId(): ?string
    {
        return $this->mainVariantId;
    }

    /**
     * @return list<array{id: string, representation?: string, expressionForListings?: bool}>|null
     */
    public function getConfiguratorGroupConfig(): ?array
    {
        return $this->configuratorGroupConfig;
    }
}
