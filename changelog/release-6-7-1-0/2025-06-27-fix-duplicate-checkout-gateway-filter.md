---
title: Fix duplicate checkout gateway filter
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Changed `Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute` to directly use the `paymentMethodRoute` & `shippingMethodRoute` as they are already filtered by the `onlyAvailable` parameter
