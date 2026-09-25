---
title: add cart rule loader extension event
issue: #11282
author: Michel Bade
author_email: m.bade@shopwell.com
author_github: @cyl3x
---
# Core
* Added `Shopwell\Core\Checkout\Cart\Extension\CheckoutCartRuleLoaderExtension` to be dispatched in the `CartRuleLoader`.
* Changed `Shopwell\Core\Checkout\Cart\CartRuleLoader` to dispatch `CheckoutCartRuleLoaderExtension`.
