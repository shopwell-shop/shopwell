---
title: Use reference id in line item rules
issue: NEXT-13475
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopwell\Core\Checkout\Cart\Rule\LineItemWithQuantityRule` to use the `\Shopwell\Core\Checkout\Cart\LineItem\LineItem::$referencedId` instead of the `\Shopwell\Core\Checkout\Cart\LineItem\LineItem::$id`
