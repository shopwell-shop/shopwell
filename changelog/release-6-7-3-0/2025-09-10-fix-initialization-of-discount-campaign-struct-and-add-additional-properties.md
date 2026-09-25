---
title: Fix initialization of DiscountCampaignStruct and add additional properties 
author: Kai Gossel
author_email: k.gossel@shopwell.com
author_github: kaigossel
---
# Core
* Changed `\Shopwell\Core\Framework\Store\Struct\VariantStruct::fromArray` to initialize discount campaign objects by calling `\Shopwell\Core\Framework\Store\Struct\DiscountCampaignStruct::fromArray`.
* Changed `\Shopwell\Core\Framework\Store\Struct\DiscountCampaignStruct::fromArray` to correctly initialize `\Shopwell\Core\Framework\Store\Struct\DiscountCampaignStruct::$startDate` and `\Shopwell\Core\Framework\Store\Struct\DiscountCampaignStruct::$endDate` as `DateTimeImmutable`.
* Added additional `\Shopwell\Core\Framework\Store\Struct\VariantStruct` properties `$duration`, `$netPricePerMonth` for future use
* Added additional `\Shopwell\Core\Framework\Store\Struct\DiscountCampaignStruct` property `$discountedPricePerMonth` for future use
___
# Administration
* Changed TypeScript interfaces in `module/sw-extension/service/extension-store-action.service.ts` to match properties for `\Shopwell\Core\Framework\Store\Struct\VariantStruct`, `\Shopwell\Core\Framework\Store\Struct\DiscountCampaignStruct`
