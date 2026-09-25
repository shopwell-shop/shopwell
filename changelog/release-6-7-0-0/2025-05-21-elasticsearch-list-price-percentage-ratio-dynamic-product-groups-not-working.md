---
title: Elasticsearch list/price percentage ratio dynamic product groups not working
issue: 5899
---
# Core
* Changed `parseFilter` method in `Shopwell\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser` to handle special query for cheapestPrice field
* Changed `Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater` to reindex variants when changing the variant's cheapest price
