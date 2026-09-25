---
title: Changed `PromotionGatewayInterface` return type to `PromotionCollection`
issue: NEXT-38798
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed the return type of the `Shopwell\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface` from `Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection<PromotionEntity>` to `Shopwell\Core\Checkout\Promotion\PromotionCollection`, which will be adjusted in the next major Shopwell version
* Changed the return type of the `Shopwell\Core\Checkout\Promotion\Gateway\PromotionGateway` from `Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection<PromotionEntity>` to `Shopwell\Core\Checkout\Promotion\PromotionCollection`, which will be adjusted in the next major Shopwell version
* Changed some internals of the `Shopwell\Core\Checkout\Promotion\Cart\PromotionCollector`
* Deprecated the return type of `Shopwell\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface` to change from `EntityCollection<PromotionEntity>` to `PromotionCollection`
___
# Next Major Version Changes
## Changed PromotionGatewayInterface
* Changed the return type of the `Shopwell\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface` from `EntityCollection<PromotionEntity>` to `PromotionCollection`
