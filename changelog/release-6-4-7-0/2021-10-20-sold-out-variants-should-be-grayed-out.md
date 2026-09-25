---
title: Sold out variants should be grayed out
issue: NEXT-15280
author_github: @Dominik28111
---
# Core
* Deprecated method `Shopwell\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult::addCombination()`, parameter `$available`will be mandatory with 6.5.0.
* Changed method `Shopwell\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()` to load stock and closeout of products to check whether it's available or not.
* Added method `Shopwell\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult::isAvailable()`.
* Added property `$combinationDetails` to `Shopwell\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult::isAvailable()`.
