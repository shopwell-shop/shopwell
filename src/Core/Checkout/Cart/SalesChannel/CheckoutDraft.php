<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
use Shopwell\Core\Checkout\Customer\ProspectiveCustomer;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\SalesChannel\Context\LanguageInfo;

/**
 * The recalculated checkout of the current context, as returned by `/store-api/checkout/draft`.
 * The optional parts are `null` when the request did not ask for them.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class CheckoutDraft extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly ?ProspectiveCustomer $prospectiveCustomer,
        public readonly ShippingMethodEntity $shippingMethod,
        public readonly PaymentMethodEntity $paymentMethod,
        public readonly CurrencyEntity $currency,
        public readonly LanguageInfo $language,
        public readonly ?ShippingMethodCollection $shippingMethods,
        public readonly ?PaymentMethodCollection $paymentMethods,
        public readonly ?ShippingCostCollection $shippingCosts,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'checkout_draft';
    }
}
