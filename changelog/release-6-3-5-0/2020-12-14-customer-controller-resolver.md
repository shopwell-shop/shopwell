---
title: Customer controller resolver
issue: NEXT-12348
---
# Core
* Added `CustomerValueResolver` class at `Shopwell\Core\Checkout\Customer`, for resolving a customer in the SaleChannelContext, it's required LoginRequired and SalesChannelContext
* Added `Customer $customer` parameter in store api routes. The parameter will be required in 6.4. At the moment the parameter is commented out in the `*AbstractRoute`, but the following parameters are already passed:
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractAddWishlistProductRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangePasswordRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangePaymentMethodRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractDeleteAddressRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractDeleteCustomerRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractMergeWishlistProductRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractRemoveWishlistProductRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractSwitchDefaultAddressRoute`
    * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute`
___
# Storefront
* Changed controller signature to inject customer over new controller resolver.
___
# Upgrade Information

## Require CustomerEntity parameter in store api routes

* Added `CustomerEntity $customer` parameter in store api routes. The parameter will be required in 6.4. At the moment, the parameter is commented out in the `*AbstractRoute`, but it is already passed. If you decorate on of the following routes, you have to change your sources as follows:
    * Affected routes:
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractAddWishlistProductRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangePasswordRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangePaymentMethodRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractDeleteAddressRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractDeleteCustomerRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractMergeWishlistProductRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractRemoveWishlistProductRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractSwitchDefaultAddressRoute`
        * `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute`
    * Sources before:
        ```
        /**
         * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer", methods={"GET"})
         */
        public function load(Request $request, SalesChannelContext $context): CustomerResponse
        {
            $criteria = $this->requestCriteriaBuilder->handleRequest(
                $request,
                new Criteria(),
                $this->customerDefinition,
                $context->getContext()
                );
        }      
        ```
    * Sources after:
        ```
        use Shopwell\Core\Checkout\Customer\CustomerEntity;

        /**
         * 
         * @LoginRequired()
         * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer", methods={"GET"})
         */
        public function load(Request $request, SalesChannelContext $context, CustomerEntity $customer = null): CustomerResponse
        {
            // remove this code with, 6.4.0. The customer will be required in this version
            if (!$customer) {
                $customer = $context->getCustomer();
            }
        }
        ```
