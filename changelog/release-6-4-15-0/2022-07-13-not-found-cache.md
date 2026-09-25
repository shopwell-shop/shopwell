---
title: Not found cache
issue: NEXT-22404
author: Soner Sayakci
author_email: s.sayakci@shopwell.com
---

# Storefront
* Added `\Shopwell\Storefront\Framework\Routing\NotFound\NotFoundSubscriber` to handle 404 pages and cache the page.
  * `\Shopwell\Storefront\Framework\Routing\NotFound\NotFoundPageCacheKeyEvent` can be used to manipulate the cache key
  * `\Shopwell\Storefront\Framework\Routing\NotFound\NotFoundPageTagsEvent` can be used to manipulate the cache tags

