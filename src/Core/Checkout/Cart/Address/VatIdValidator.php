<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Address;

use Shopwell\Core\Checkout\Cart\Address\Error\VatIdMissingError;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class VatIdValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();

        if ($customer === null || $customer->getAccountType() !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return;
        }

        if (!$customer->getActiveBillingAddress()?->getCountry()?->getVatIdRequired()) {
            return;
        }

        if (\array_filter($customer->getVatIds() ?? []) !== []) {
            return;
        }

        $errors->add(new VatIdMissingError());
    }
}
