<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Address;

use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressCountryRegionMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

#[Package('checkout')]
class AddressValidator implements CartValidatorInterface, ResetInterface
{
    /**
     * @var array<string, bool>
     */
    private array $available = [];

    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<Entity>> $salesChannelCountryRepository
     */
    public function __construct(private readonly EntityRepository $salesChannelCountryRepository)
    {
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $country = $context->getShippingLocation()->getCountry();
        $customer = $context->getCustomer();

        $isPhysicalLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $isPhysicalLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_PHYSICAL);

            Feature::callSilentIfInactive('v6.8.0.0', static function () use ($cart, &$isPhysicalLineItem): void {
                $isPhysicalLineItem = $isPhysicalLineItem || $cart->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL);
            });
        }

        $validateShipping = $cart->getLineItems()->count() === 0 || $isPhysicalLineItem;

        if (!$country->getActive() && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name'), $context->getShippingLocation()->getAddress()?->getId()));

            return;
        }

        if (!$country->getShippingAvailable() && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name'), $context->getShippingLocation()->getAddress()?->getId()));

            return;
        }

        if (!$this->isSalesChannelCountry($country->getId(), $context) && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name'), $context->getShippingLocation()->getAddress()?->getId()));

            return;
        }

        if ($customer === null) {
            return;
        }

        $activeBillingAddress = $customer->getActiveBillingAddress();
        $activeShippingAddress = $customer->getActiveShippingAddress();

        if ($activeBillingAddress === null) {
            $errors->add(new BillingAddressMissingError());
        }

        // not gated by $validateShipping: a digital-only cart creates no delivery, so nothing else would stop it being ordered against a substituted address
        if ($activeShippingAddress === null) {
            $errors->add(new ShippingAddressMissingError());
        }

        if ($activeBillingAddress === null || $activeShippingAddress === null) {
            // No need to add salutation-specific errors in this case
            return;
        }

        if (!$activeBillingAddress->getSalutationId()) {
            $errors->add(new BillingAddressSalutationMissingError($activeBillingAddress));

            return;
        }

        if (!$activeShippingAddress->getSalutationId() && $validateShipping) {
            $errors->add(new ShippingAddressSalutationMissingError($activeShippingAddress));
        }

        if ($activeBillingAddress->getCountry()?->getForceStateInRegistration()) {
            if (!$activeBillingAddress->getCountryState()) {
                $errors->add(new BillingAddressCountryRegionMissingError($activeBillingAddress));
            }
        }

        if ($activeShippingAddress->getCountry()?->getForceStateInRegistration()) {
            if (!$activeShippingAddress->getCountryState()) {
                $errors->add(new ShippingAddressCountryRegionMissingError($activeShippingAddress));
            }
        }
    }

    public function reset(): void
    {
        $this->available = [];
    }

    private function isSalesChannelCountry(string $countryId, SalesChannelContext $context): bool
    {
        if (isset($this->available[$countryId])) {
            return $this->available[$countryId];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
            ->addFilter(new EqualsFilter('countryId', $countryId));

        return $this->available[$countryId] = $this->salesChannelCountryRepository->searchIds($criteria, $context->getContext())->getTotal() !== 0;
    }
}
