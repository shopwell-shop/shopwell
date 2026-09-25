---
title: Checkout Gateway
issue: NEXT-30813
author: Lennart Tinkloh
author_email: l.tinkloh@shopwell.com
author_github: "@lernhart"
---
# Core
* Added checkout gateway feature, which allows apps to manipulate the available payment and shipping methods during the checkout process, based off decisions made on the app server. It also allows to add cart errors during checkout. 
* Added `Shopwell\Core\Checkout\Gateway\CheckoutGatewayInterface` to allow extensions to integrate a custom implementation of the checkout gateway.
* Added `Shopwell\Core\Framework\App\Checkout\Gateway\AppCheckoutGateway` as a default app-system only implementation of the `CheckoutGatewayInterface`.
* Added `Shopwell\Core\Checkout\Gateway\Event\CheckoutGatewayCommandsCollectedEvent` to allow plugins to manipulate the commands before they are executed.
* Added `Shopwell\Core\Framework\Log\ExceptionLogger` to allow logging or throwing an exception based on the `APP_ENV` environment. It is possible to set the `LOGGER_ENFORCE_THROW_EXCEPTION` ENV variable to enforce throwing all exception.
___
# API
* Added `Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute` Store-API route to handle checkout gateway.
* Changed `Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRoute` to make use of the new checkout gateway.
* Deprecated `Shopwell\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute::order` method. A new mandatory `request` parameter will be introduced.
* Deprecated `onlyAvailable` flag in `Shopwell\Storefront\Page\Account\Order\AccountEditOrderPageLoader` in favor of the new checkout gateway.
* Deprecated `onlyAvailable` flag in `Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute` in favor of the new checkout gateway.
___
# Storefront
* Changed `Shopwell\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader` to not include `onlyAvailable` flag in the request for payment and shipping methods. This is done by default in the `CheckoutGatewayRoute` instead.
* Changed `Shopwell\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader` to make use of the new checkout gateway.
* Changed `Shopwell\Storefront\Page\Account\Order\AccountEditOrderPageLoader` to make use of the new checkout gateway.
___
# Next Major Version Changes
## onlyAvailable flag removed
* The `onlyAvailable` flag in the `Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute` in the request will be removed in the next major version. The route will always filter the payment and shipping methods before calling the checkout gateway based on availability.
## AbstractCartOrderRoute::order method signature change
* The `Shopwell\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute::order` method will change its signature in the next major version. A new mandatory `request` parameter will be introduced.
