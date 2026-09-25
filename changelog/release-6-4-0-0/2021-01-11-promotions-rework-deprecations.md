---
title: Promotions Rework deprecations
issue: NEXT-12016
---
# Core
* Added `Shopwell\Core\Checkout\Promotion\Util\PromotionCodeService` and `Shopwell\Core\Checkout\Promotion\Api\PromotionController`
* Added Exceptions in `Shopwell\Core\Checkout\Promotion\Exception`:
  * `PatternNotComplexEnoughException`
  * `PatternAlreadyInUseException`
* Deprecated `Shopwell\Core\Checkout\Promotion\Util\PromotionCodesLoader` and `Shopwell\Core\Checkout\Promotion\Util\PromotionCodesRemover` for tag:v6.4.0.0. Use the EntityRepository or PromotionCodeService instead.
___
# API
* Deprecated `Shopwell\Core\Checkout\Promotion\Api\PromotionActionController` for tag:v6.4.0.0. Use the PromotionController instead.
