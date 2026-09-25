<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopwell\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class TestGenericExceptionTaxProvider extends AbstractTaxProvider
{
    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        throw new \Exception('Test exception');
    }
}
