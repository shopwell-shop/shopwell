---
title: Improve data hash for calculation of product prices in cart
issue: NEXT-37673
---

# Core

* Changed `\Shopwell\Core\Checkout\Cart\CartRuleLoader` to save the cart if data hash is changed
* Changed `\Shopwell\Core\Content\Product\Cart\ProductCartProcessor` to calculate the data hash consistently for the product data
