---
title: Order Placed Criteria Event
issue: NEXT-16600
author: Konstantin Kiritsenko
author_email: k@componentk.com
author_github: @augsteyer
---
# Core
* Added event `Shopwell\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent`.
* Changed method `Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRoute::order()` to fire `CheckoutOrderPlacedCriteriaEvent`.
