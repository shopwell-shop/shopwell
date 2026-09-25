---
title: Fixed order date being changed when recalculating
issue: NEXT-16475
author: Max Stegmeyer
---
# Core
* Changed `Shopwell\Core\Checkout\Cart\Order\RecalculationService` to not update the order date when recalculating
* Added option `shouldIncludeOrderDate` to `Shopwell\Core\Checkout\Cart\Order\OrderConversionContext`
* Added parameter `setOrderDate` to `Shopwell\Core\Checkout\Cart\Order\Transformer\CartTransformer`
* Changed `Shopwell\Core\Checkout\Cart\Order\OrderConverter` to respect context option for setting order date
