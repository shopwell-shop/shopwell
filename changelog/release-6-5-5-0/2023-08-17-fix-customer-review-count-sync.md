---
title: Fix customer review count sync
issue: NEXT-29967
---
# Core
* Changed `\Shopwell\Core\Checkout\Customer\Service\ProductReviewCountService::updateReviewCount()` to be idempotent, thus solving issues that the customer review count may get out of sync.