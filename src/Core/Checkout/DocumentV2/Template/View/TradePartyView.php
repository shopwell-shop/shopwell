<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Template\View;

use Shopwell\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopwell\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * Precomputed view of a buyer trade party derived from an order.
 *
 * Sellers are sourced from {@see DocumentCompanyInfo}, not this view.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class TradePartyView
{
    public function __construct(
        public ?string $id,
        public string $name,
        public ?string $street,
        public ?string $additionalAddressLine1,
        public ?string $additionalAddressLine2,
        public ?string $zipcode,
        public ?string $city,
        public ?string $countrySubdivision,
        public ?string $countryIso,
        public ?string $email,
    ) {
    }

    public static function buyerFromOrder(OrderEntity $order): self
    {
        $customer = $order->getOrderCustomer();
        $billing = $order->getBillingAddress()
            ?? $order->getAddresses()?->get($order->getBillingAddressId());

        $name = trim($customer?->getName() ?? '');

        if ($customer?->getCompany()) {
            $name = trim($name . ' - ' . $customer->getCompany());
        }

        if ($name === '') {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'orderCustomer.name',
                'Buyer name is empty.',
            );
        }

        if ($billing === null) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'billingAddress',
                'Buyer billing address is missing.',
            );
        }

        if (!$billing->getCountry()?->getIso()) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'billingAddress.country',
                'Buyer billing address country is missing.',
            );
        }

        return new self(
            id: $customer?->getCustomerNumber(),
            name: $name,
            street: $billing->getStreet(),
            additionalAddressLine1: $billing->getAdditionalAddressLine1(),
            additionalAddressLine2: $billing->getAdditionalAddressLine2(),
            zipcode: $billing->getZipcode(),
            city: $billing->getCity(),
            countrySubdivision: $billing->getCountryState()?->getName(),
            countryIso: $billing->getCountry()->getIso(),
            email: $customer?->getEmail(),
        );
    }
}
