---
title: Fix shipping method selection in cart page
issue: NEXT-11960
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com 
author_github: Oliver Skroblin
---
# Storefront
* Changed `\Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader::getShippingMethods`, to always contains the current selected shipping method  
