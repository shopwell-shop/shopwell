---
title: Fix entity aggregator cache
issue: NEXT-11269
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com 
author_github: @OliverSkroblin
---
# Core
* Removed criteria.extensions from aggregation cache key in `\Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getAggregationCacheKey`
* Changed injected repository of `\Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer` to index customers instead of products  
