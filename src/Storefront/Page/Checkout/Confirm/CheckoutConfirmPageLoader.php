<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Checkout\Confirm;

use Shopwell\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\State;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\BuildValidationEvent;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class CheckoutConfirmPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StorefrontCartFacade $cartService,
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly DataValidationFactoryInterface $addressValidationFactory,
        private readonly DataValidator $validator,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context): CheckoutConfirmPage
    {
        $page = $this->genericPageLoader->load($request, $context);
        $page = CheckoutConfirmPage::createFrom($page);
        $this->setMetaInformation($page);

        $cartGatewayResult = $this->cartService->getWithCheckoutGateway($request, $context->getToken(), $context, false, true);
        $cart = $cartGatewayResult->cart;
        $response = $cartGatewayResult->gatewayResponse;

        $page->setPaymentMethods($response->getPaymentMethods());
        $page->setShippingMethods($response->getShippingMethods());

        $this->validateCustomerAddresses($cart, $context);
        $page->setCart($cart);

        $isDownloadLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_DIGITAL);
        $isPhysicalLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            Feature::callSilentIfInactive('v6.8.0.0', static function () use ($cart, &$isDownloadLineItem, &$isPhysicalLineItem): void {
                $isDownloadLineItem = $isDownloadLineItem || $cart->getLineItems()->hasLineItemWithState(State::IS_DOWNLOAD);
                $isPhysicalLineItem = $isPhysicalLineItem || $cart->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL);
            });
        }

        $page->setShowRevocation($isDownloadLineItem);
        $page->setHideShippingAddress(!$isPhysicalLineItem);

        $this->eventDispatcher->dispatch(
            new CheckoutConfirmPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    protected function setMetaInformation(CheckoutConfirmPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('checkout.confirmMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    private function validateCustomerAddresses(Cart $cart, SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw CartException::customerNotLoggedIn();
        }

        $billingAddress = $customer->getActiveBillingAddress();
        $shippingAddress = $customer->getActiveShippingAddress();

        $this->validateBillingAddress($billingAddress, $cart, $context);
        $this->validateShippingAddress($shippingAddress, $billingAddress, $cart, $context);
    }

    private function validateBillingAddress(
        ?CustomerAddressEntity $billingAddress,
        Cart $cart,
        SalesChannelContext $context
    ): void {
        $validation = $this->addressValidationFactory->create($context);
        if ($billingAddress) {
            $validation->set('zipcode', new CustomerZipCode(countryId: $billingAddress->getCountryId()));
        }

        $validationEvent = new BuildValidationEvent($validation, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        if ($billingAddress === null) {
            return;
        }

        $violations = $this->validator->getViolations($billingAddress->jsonSerialize(), $validation);

        if ($violations->count() > 0) {
            $cart->getErrors()->add(new AddressValidationError(true, $violations, $billingAddress->getId()));
        }
    }

    private function validateShippingAddress(
        ?CustomerAddressEntity $shippingAddress,
        ?CustomerAddressEntity $billingAddress,
        Cart $cart,
        SalesChannelContext $context
    ): void {
        $validation = $this->addressValidationFactory->create($context);
        if ($shippingAddress) {
            $validation->set('zipcode', new CustomerZipCode(countryId: $shippingAddress->getCountryId()));
        }

        $validationEvent = new BuildValidationEvent($validation, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        if ($shippingAddress === null) {
            return;
        }

        if ($billingAddress !== null && $shippingAddress->getId() === $billingAddress->getId()) {
            return;
        }

        $violations = $this->validator->getViolations($shippingAddress->jsonSerialize(), $validation);
        if ($violations->count() > 0) {
            $cart->getErrors()->add(new AddressValidationError(false, $violations, $shippingAddress->getId()));
        }
    }
}
