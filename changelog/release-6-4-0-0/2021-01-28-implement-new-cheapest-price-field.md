---
title: Implement new cheapest price field
issue: NEXT-12169
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Added `\Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice`
* Added `\Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice`
* Added `\Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder`
* Added `\Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer`
* Added `\Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField`
* Added `\Shopwell\Core\Content\Product\DataAbstractionLayer\AbstractCheapestPriceQuantitySelector`
* Added `\Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater` 
* Added `\Shopwell\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection::filterByRuleId` 
* Added `\Shopwell\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection::sortByQuantity` 
* Added `\Shopwell\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator` 
* Added `\Shopwell\Core\Content\Product\SalesChannel\Price\ReferencePriceDto` 
* Added `\Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity::$calculatedCheapestPrice`
* Added `\Shopwell\Core\Content\Product\ProductEntity::$cheapestPrice`, which contains the cheapest available price
* Added `\Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer`, which can be used for php serialized values
* Added `\Shopwell\Core\Framework\DataAbstractionLayer\VersionManager::DISABLE_AUDIT_LOG`, which allows to disable the audit log be written
* Added `\Shopwell\Core\System\SalesChannel\SalesChannelContext::getCurrencyId` 
* Deprecated `\Shopwell\Core\Content\Product\DataAbstractionLayer\Indexing\ListingPriceUpdater`, will be removed
* Deprecated `\Shopwell\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface`, use `AbstractProductPriceCalculator` instead
* Deprecated `\Shopwell\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitions`, will be removed
* Deprecated `\Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity::$calculatedListingPrice`, use `calculatedCheapestPrice` instead
* Deprecated `\Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity::getCalculatedListingPrice`, use `calculatedCheapestPrice` instead
* Deprecated `\Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity::setCalculatedListingPrice`, use `calculatedCheapestPrice` instead
* Deprecated `\Shopwell\Core\Content\Product\ProductEntity::$grouped`, will be removed
* Deprecated `\Shopwell\Core\Content\Product\ProductEntity::setGrouped`, will be removed
* Deprecated `\Shopwell\Core\Content\Product\ProductEntity::isGrouped`, will be removed
* Deprecated `\Shopwell\Core\Content\Product\ProductEntity::$listingPrices`, use `cheapestPrice` instead
* Deprecated `\Shopwell\Core\Content\Product\ProductEntity::getListingPrices`, use `cheapestPrice` instead
* Deprecated `\Shopwell\Core\Content\Product\ProductEntity::setListingPrices`, use `cheapestPrice` instead
