---
title: Fix category id update in line item
issue: NEXT-31519
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
---

# Core
* Changed `\Shopwell\Core\Content\Product\Cart\ProductCartProcessor` to correctly replace arrays in line item payloads.
* Changed `\Shopwell\Core\Checkout\Cart\LineItem\LineItem::replacePayload` to not replace recursively anymore, just on the first level.
___
# Upgrade Information
## LineItem payload replacement behavior

The method `\Shopwell\Core\Checkout\Cart\LineItem\LineItem::replacePayload` does not do a recursive replacement of the payload anymore, but replaces the payload only on a first level.

Therefore, subarrays of the payload may reduce in items instead of being only added to.
