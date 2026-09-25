---
title: Add more isEmpty condition for Shipping postal cost
issue: NEXT-12482
---
# Core
* Added new const operators `empty` in `Administration/Resource/app/administration/src/app/service/rule-condition.service.js`
* Added new const `OPERATOR_EMPTY` in `Shopwell\Core\Framework\Rule\Rule`
___
# Administration
* Changed component to hide value when select is empty in: 
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-zip-code`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-days-since-last-order`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-billing-country`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-billing-street`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-billing-zip-code`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-customer-tag`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-last-name`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-country`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-street`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-tag`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-of-manufacturer`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-purchase-price`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-release-date`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-in-category`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-width`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-height`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-length`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-weight`
* Changed method `match` to support empty case and method `getConstraints` to change condition validate when create rule in : 
  `Shopwell\Core\Checkout\Customer\Rule\ShippingZipCodeRule`
  `Shopwell\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule`
  `Shopwell\Core\Checkout\Customer\Rule\BillingCountryRule`
  `Shopwell\Core\Checkout\Customer\Rule\BillingStreetRule`
  `Shopwell\Core\Checkout\Customer\Rule\BillingZipCodeRule`
  `Shopwell\Core\Checkout\Customer\Rule\CustomerTagRule`
  `Shopwell\Core\Checkout\Customer\Rule\LastNameRule`
  `Shopwell\Core\Checkout\Customer\Rule\ShippingCountryRule`
  `Shopwell\Core\Checkout\Customer\Rule\ShippingStreetRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemTagRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemReleaseDateRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemInCategoryRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemDimensionLengthRule`
  `Shopwell\Core\Checkout\Cart\Rule\LineItemDimensionWeightRule`
