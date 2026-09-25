---
title: Added payment token invalidation
issue: NEXT-14739
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Added new `\Shopwell\Core\Checkout\Payment\Exception\TokenInvalidatedException` which is thrown if the payment token already used to finalize a transaction.
* Changed `\Shopwell\Core\Checkout\Payment\Controller\PaymentController::finalizeTransaction` exception handling, all `ShopwellHttpException` are now redirected to the provided error url 
