---
title: Add cleanup task for cart table
issue: NEXT-14155
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Added `\Shopwell\Core\Checkout\Cart\Cleanup\CleanupCartTaskHandler`, which delete all carts which are older than 120(`shopwell.cart.expire_days`) days
* Added `shopwell.cart.expire_days` config to define expire time for cart table entries
