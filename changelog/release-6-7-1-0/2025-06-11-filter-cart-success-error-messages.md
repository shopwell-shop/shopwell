---
title: Filter cart success error messages
issue: #10146
---
# Storefront
* Changed error convert behaviour in `\Shopwell\Storefront\Controller\CartLineItemController`. `\Shopwell\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError` will be converted into a success message on every `CartLineItemController` route.
