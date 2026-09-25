---
title: Added product review form send event
issue: NEXT-13597
author: d.neustadt
author_email: d.neustadt@shopwell.com
author_github: dneustadt
---
# Core
* Added `Shopwell\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent` that is dispatched when the product review form is submitted
* Added `Shopwell\Core\Content\Flow\Dispatching\Storer\ReviewFormDataStorer` for storing the data of a review form within a flow sequence
* Added `Shopwell\Core\Content\Flow\Dispatching\Storer\ProductStorer` for storing product data within a flow sequence
* Added interfaces `Shopwell\Core\Content\Flow\Dispatching\Aware\ReviewFormDataAware` and `Shopwell\Core\Framework\Event\ProductAware`
