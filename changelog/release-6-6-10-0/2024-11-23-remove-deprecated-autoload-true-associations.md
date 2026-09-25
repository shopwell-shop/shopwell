---
title: Remove deprecated autoload === true associations
issue: NEXT-25333
---
# Core
* Changed `Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition` to remove deprecated autoload === true for properties:
  * `deliveryTime`
  * `appShippingMethod`
* Changed `Shopwell\Core\Checkout\Payment\PaymentMethodDefinition` to remove deprecated autoload === true for `appPaymentMethod`.
* Changed `Shopwell\Core\System\NumberRange\NumberRangeDefinition` to remove deprecated autoload === true for `state`.
* Changed `Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition` to remove deprecated autoload === true for `appScriptCondition`.
* Changed `Shopwell\Core\Content\Product\ProductDefinition` to remove deprecated autoload === true for `tax`.
* Changed `Shopwell\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition` to remove deprecated autoload === true for `media`.
