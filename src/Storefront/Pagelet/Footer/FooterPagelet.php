<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Footer;

use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\Tree\Tree;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Pagelet\NavigationPagelet;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class FooterPagelet extends NavigationPagelet
{
    /**
     * @internal
     */
    public function __construct(
        ?Tree $navigation,
        protected CategoryCollection $serviceMenu,
        protected PaymentMethodCollection $paymentMethods,
        protected ShippingMethodCollection $shippingMethods,
    ) {
        parent::__construct($navigation);
    }

    public function getServiceMenu(): CategoryCollection
    {
        return $this->serviceMenu;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shippingMethods;
    }
}
