---
title: Add payment method handler runtime fields
issue: NEXT-17157
author: Lennart Tinkloh
author_email: l.tinkloh@shopwell.com 
author_github: @lernhart
---
# Core
* Added `synchronous` runtime field to `Shopwell\Core\Checkout\Payment\PaymentMethodDefinition`.
* Added `synchronous` property to `Shopwell\Core\Checkout\Payment\PaymentMethodEntity`.
* Added `asynchronous` runtime field to `Shopwell\Core\Checkout\Payment\PaymentMethodDefinition`.
* Added `asynchronous` property to `Shopwell\Core\Checkout\Payment\PaymentMethodEntity`.
* Added `prepared` runtime field to `Shopwell\Core\Checkout\Payment\PaymentMethodDefinition`.
* Added `prepared` property to `Shopwell\Core\Checkout\Payment\PaymentMethodEntity`.
* Changed `Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber` to update the runtime fields whenever the payment handler inherits the corresponding interface. 
