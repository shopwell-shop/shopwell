---
title: Respect missing shipping method currency prices
issue: #10696
author: Michel Bade
author_email: m.bade@shopwell.com
author_github: @cyl3x
---
# Core
* Changed `Shopwell\Core\Checkout\Cart\Delivery\DeliveryCalculator` to respect missing currency prices within the price matrices of a shipping method.
