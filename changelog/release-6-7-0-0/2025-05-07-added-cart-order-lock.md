---
title: Added locking mechanism to CartOrderRoute
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
author_github: @mstegmeyer
---
# Core
* Changed `Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRoute` to use a lock for converting a cart to an order to prevent duplicate orders.
