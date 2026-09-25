---
title: Fix ElasticSearch indexing exception on order updates
issue: NEXT-39651
---
# Core
* Changed `Shopwell\Core\Content\Product\Stock\StockStorage` so that `Shopwell\Core\Content\Product\Events\ProductStockAlteredEvent` is not dispatched when no changes happened.
