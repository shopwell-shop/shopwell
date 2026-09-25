<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopwell\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class TestEmptyTaxProvider extends AbstractTaxProvider
{
    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        $data = [
            'lineItemTaxes' => [
                'line-item-1' => new CalculatedTaxCollection(),
                'line-item-2' => new CalculatedTaxCollection(),
            ],
            'deliveryTaxes' => [
                'delivery-1' => new CalculatedTaxCollection(),
                'delivery-2' => new CalculatedTaxCollection(),
            ],
            'cartPriceTaxes' => new CalculatedTaxCollection(),
        ];

        /** @var TaxProviderResult $taxProviderStruct */
        $taxProviderStruct = TaxProviderResult::createFrom(new ArrayStruct($data));

        return $taxProviderStruct;
    }
}
