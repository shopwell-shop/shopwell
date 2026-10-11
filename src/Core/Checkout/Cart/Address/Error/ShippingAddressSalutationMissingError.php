<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Address\Error;

use Shopwell\Core\Checkout\Cart\Error\ErrorRoute;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingAddressSalutationMissingError extends SalutationMissingError
{
    protected const KEY = parent::KEY . '-shipping-address';

    public function __construct(
        private readonly CustomerAddressEntity $address
    ) {
        $this->message = \sprintf(
            'A salutation needs to be defined for the shipping address "%s, %s %s".',
            $address->getName(),
            (string) $address->getZipcode(),
            $address->getCity()
        );

        $this->parameters = [
            'addressId' => $address->getId(),
        ];

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return self::KEY;
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed without replacement
     */
    public function getRoute(): ?ErrorRoute
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, 'getRoute', 'v6.8.0.0'));

        return new ErrorRoute(
            /** @phpstan-ignore shopwell.storefrontRouteUsage (Do not use Storefront routes in the core. Will be fixed with https://github.com/shopwell-shop/shopwell/issues/12969) */
            'frontend.account.address.edit.page',
            $this->parameters
        );
    }

    public function getAddressId(): ?string
    {
        return $this->address->getId();
    }
}
