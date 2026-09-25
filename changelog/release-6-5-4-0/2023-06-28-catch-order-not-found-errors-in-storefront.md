---
title: Catch order not found errors in Storefront
issue: NEXT-28434
---
# Storefront
* Removed exception throw of `\Shopwell\Core\Checkout\Order\OrderException::orderNotFound` in `\Shopwell\Storefront\Controller\AccountOrderController::editOrder` and replaced it with a flash message
