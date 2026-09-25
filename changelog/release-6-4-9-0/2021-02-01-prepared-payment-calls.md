---
title: Prepared payment add pre-order call
issue: NEXT-17164
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
---
# Core
* Added `Shopwell\Core\Checkout\Payment\PreparedPaymentService` to handle prepared payment calls from routes.
* Added `Shopwell\Core\Checkout\Payment\Cart\PreparedPaymentProcessor` to call prepared payment handler.
* Changed `Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRoute` to call `validate` method of prepared payments before persisting the order.
* Changed `Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRoute` to call `capture` method of prepared payments before persisting the order.
* Added `Shopwell\Core\Framework\App\Payment\Handler\AppPreparedPaymentHandler` allow prepared payments from apps.
* Added `validateUrl` and `captureUrl` to payment methods in app manifest.
