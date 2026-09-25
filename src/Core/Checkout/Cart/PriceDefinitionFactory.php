<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart;

use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class PriceDefinitionFactory
{
    /**
     * @param array<string, mixed> $priceDefinition
     */
    public function factory(Context $context, array $priceDefinition, string $lineItemType): PriceDefinitionInterface
    {
        if (!isset($priceDefinition['type'])) {
            throw CartException::invalidPriceFieldTypeException('none');
        }

        return match ($priceDefinition['type']) {
            QuantityPriceDefinition::TYPE => QuantityPriceDefinition::fromArray($priceDefinition),
            AbsolutePriceDefinition::TYPE => new AbsolutePriceDefinition((float) $priceDefinition['price']),
            PercentagePriceDefinition::TYPE => new PercentagePriceDefinition($priceDefinition['percentage']),
            default => throw CartException::invalidPriceFieldTypeException($priceDefinition['type']),
        };
    }
}
