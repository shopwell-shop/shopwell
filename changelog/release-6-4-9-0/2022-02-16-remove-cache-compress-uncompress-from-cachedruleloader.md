---
title: Remove cache compress/uncompress from CachedRuleLoader
issue: NEXT-20149
author: Krispin Lütjann
author_email: k.luetjann@shopwell.com
author_github: King-of-Babylon
---
# Core
* Changed the `Checkout/Cart/CachedRuleLoader.php` to not use the `CacheCompressor::uncompress` and `CacheCompressor::compress` functions
