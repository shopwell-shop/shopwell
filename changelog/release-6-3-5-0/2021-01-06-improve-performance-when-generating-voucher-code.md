---
title: Improve performance when generating voucher code
issue: NEXT-12818
---
# Core
* Added a new function `isGeneratingIndividualCode` in `\Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer` to check if the operation is generating voucher code so we can skip running `\Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer::handle` function.
