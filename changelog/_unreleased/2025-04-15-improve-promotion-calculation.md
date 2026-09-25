---
title: Improve promotion calculation
issue: #8329
flag: PERFORMANCE_TWEAKS
---
# Core
* Changed `getTotalPriceAmount` and `getUnitPriceAmount` method visibilities to public in `\Shopwell\Core\Checkout\Cart\Price\Struct\PriceCollection`
* Added new `buildCollectionRules` method in `\Shopwell\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder`. With it, a `\Shopwell\Core\Checkout\Cart\Tax\CalculatedPrice` is no longer needed, just the total price and a `\Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection`.
* Changed `getMatchingItems` in `\Shopwell\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager`. Will only split items by quantity, if discount value `considerAdvancedRules` is `true` and `applierKey` is not "ALL". Only available with enabled feature flag "PERFORMANCE_TWEAKS" in `.env` file 
___
# Upgrade Information
## New public methods for performance improvements
### PriceCollection
If you use the `\Shopwell\Core\Checkout\Cart\Price\Struct\PriceCollection::sum()` method for a single value (e.g. `getTotalPrice`), you can now use the new public method `getUnitPriceAmount` or `getTotalPriceAmount` to gain performance improvements.
### PercentageTaxRuleBuilder
It isn't needed anymore to create a `\Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice` object to build the tax rules. With the new method `buildCollectionRules` only a `\Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection` and the total price are needed.
