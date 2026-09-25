---
title: Add app payments
issue: NEXT-14357
author: Max Stegmeyer
---

# Core

* Added following new classes:
    * `Shopwell\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister`
    * `Shopwell\Core\Framework\App\Payment\PaymentMethodStateService`
    * `Shopwell\Core/Framework/App/Manifest/Xml/PaymentMethod`
    * `Shopwell\Core/Framework/App/Manifest/Xml/Payments`
* Added following new payment handlers and corresponding payload classes for:
    * `Shopwell\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler`
    * `Shopwell\Core\Framework\App\Payment\Handler\AppSyncPaymentHandler`
* Added new entity `app_payment_method` in `Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition`
* Added association with `app_payment_method` to `media`
* Added association with `app_payment_method` to `payment_method`
* Changed `Shopwell\Core\Framework\App\AppStateService` to reflect payment method state
* Changed `Shopwell\Core\Framework\App\Lifecycle\AppLifecycle` to reflect payment method life cycle
* Changed app manifest definition and `Shopwell\Core\Framework\App\Manifest\Manifest` to add payment methods
* Changed `Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry` to return App Payment Methods
* Changed `Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry` to use `PaymentMethodEntity` instead of just handler name
* Changed `Shopwell\Core\Checkout\Payment\PaymentService` to pass more order data to payment handlers to avoid errors with SalesChannelContext
* Changed `Shopwell\Core\Checkout\Payment\PaymentService` to load app data for async payment methods
* Changed `Shopwell\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor` to load app data for sync payment methods
