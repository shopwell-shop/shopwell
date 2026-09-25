---
title: Prepared payment add pre-order call
issue: NEXT-17162
author: Lennart Tinkloh
author_email: l.tinkloh@shopwell.com
author_github: @lernhart
---
# Core
* Added `Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface` interface that all payment handler implement.
* Changed `Shopwell\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry` to register `PreparedPaymentHandlerInterfaces` as well.
