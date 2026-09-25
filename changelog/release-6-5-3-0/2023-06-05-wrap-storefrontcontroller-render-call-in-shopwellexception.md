---
title: Wrap StorefrontController render call in ShopwellException
issue: NEXT-27284
---
# Storefront
* Added `StorefrontException` class in `Shopwell\Storefront\Controller\Exception`.
* Changed `renderView` method in `Shopwell\Storefront\Controller\StorefrontController` to wrap render view in domain exception.
