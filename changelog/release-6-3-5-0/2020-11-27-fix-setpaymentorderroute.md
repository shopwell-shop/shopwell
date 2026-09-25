---
title: Replaced PaymentMethodRoute class with AbstractPaymentMethodRoute
issue: NEXT-12457
author_github: @momocode-de
---
# Core
* Changed `PaymentMethodRoute` class to `AbstractPaymentMethodRoute` in `Shopwell\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute` to ensure the extendability of the `PaymentMethodRoute` components.
