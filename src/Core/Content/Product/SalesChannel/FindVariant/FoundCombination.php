<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\FindVariant;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('inventory')]
class FoundCombination extends Struct
{
    /**
     * @param string[] $options
     */
    public function __construct(
        protected string $variantId,
        protected array $options
    ) {
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
