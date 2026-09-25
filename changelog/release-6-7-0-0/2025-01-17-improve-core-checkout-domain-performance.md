---
title: Improve core checkout domain performance
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopwell\Core\Checkout\Cart\PriceActionController` to use `PartialEntity`
* Changed `Shopwell\Core\Checkout\Cart\Order\OrderConverter` to use `PartialEntity`
* Changed `Shopwell\Core\Checkout\Cart\Order\RecalculationService` to use `searchIds`
* Changed `Shopwell\Core\Checkout\Customer\DeleteUnusedGuestCustomerService` to use `searchIds`
* Changed `Shopwell\Core\Checkout\Customer\SalesChannel\ChangePaymentMethodRoute` to use `searchIds`
* Changed `Shopwell\Core\Checkout\Customer\SalesChannel\RegisterRoute` to use `PartialEntity`
