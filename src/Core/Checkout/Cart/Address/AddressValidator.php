<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Address;

use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressGenderMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressCountryRegionMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressGenderMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressMissingError;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartValidatorInterface;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
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
        $customer = $context->getCustomer();

        $isPhysicalLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $isPhysicalLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_PHYSICAL);

            Feature::callSilentIfInactive('v6.8.0.0', static function () use ($cart, &$isPhysicalLineItem): void {
                $isPhysicalLineItem = $isPhysicalLineItem || $cart->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL);
            });
        }

        $validateShipping = $cart->getLineItems()->count() === 0 || $isPhysicalLineItem;

        if ($validateShipping && $this->addBlockedShippingLocationErrors($cart, $errors, $context)) {
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
            // No need to add gender-specific errors in this case
            return;
        }

        if (!$activeBillingAddress->getGender()) {
            $errors->add(new BillingAddressGenderMissingError($activeBillingAddress));

            return;
        }

        if (!$activeShippingAddress->getGender() && $validateShipping) {
            $errors->add(new ShippingAddressGenderMissingError($activeShippingAddress));
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

    private function addBlockedShippingLocationErrors(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): bool
    {
        $contextError = $this->getBlockedError($context->getShippingLocation(), $context);
        if ($contextError !== null) {
            $errors->add($contextError);
        }

        $blocked = $contextError !== null;
        foreach ($cart->getDeliveries() as $delivery) {
            $error = $this->getBlockedError($delivery->getLocation(), $context);
            if ($error === null || $errors->has($error->getId())) {
                continue;
            }

            $errors->add($error);
            $blocked = true;
        }

        return $blocked;
    }

    private function getBlockedError(ShippingLocation $location, SalesChannelContext $context): ?ShippingAddressBlockedError
    {
        $country = $location->getCountry();
        if ($country->getActive() && $country->getShippingAvailable() && $this->isSalesChannelCountry($country->getId(), $context)) {
            return null;
        }

        return new ShippingAddressBlockedError((string) $country->getTranslation('name'), $location->getAddress()?->getId());
    }

    private function isSalesChannelCountry(string $countryId, SalesChannelContext $context): bool
    {
        // the assignment depends on the sales channel, a key without it would reuse the result of another sales channel
        $key = $context->getSalesChannelId() . '-' . $countryId;
        if (isset($this->available[$key])) {
            return $this->available[$key];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
            ->addFilter(new EqualsFilter('countryId', $countryId));

        return $this->available[$key] = $this->salesChannelCountryRepository->searchIds($criteria, $context->getContext())->getTotal() !== 0;
    }
}
