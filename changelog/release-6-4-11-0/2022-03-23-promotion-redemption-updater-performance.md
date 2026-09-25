---
title: Improve promotion redemption updater performance
issue: NEXT-17359
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
---
# Core
* Added write protected column `promotionId` to `order_line_item` to allow for better indexing
* Changed `Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater` to only increment totals of `promotions` when order is placed
* Changed `Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater` to recalculate totals of `promotions` when indexing occurs
