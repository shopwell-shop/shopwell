---
title: Allow singular shipping address in register route
issue: NEXT-37605
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
author_github: mstegmeyer
---

# Core
* Changed `Shopwell\Core\Checkout\Customer\SalesChannel\RegisterRoute` to also accept just a ShippingAddress, then using the same address as BillingAddress.
