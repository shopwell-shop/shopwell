---
title: Improve cheapest price updater performance
issue: NEXT-16501
---
# Core
* Changed `\Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater` to only update price accessors for variants if it did change.
