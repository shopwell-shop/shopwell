---
title: Introduce entity not found message template
issue: NEXT-31378
author: Michael Telgmann
author_github: mitelg
---

# Core
* Added `Shopwell\Core\Framework\HttpException::$couldNotFindMessage` template for creating exception messages for not found entities.
* Deprecated `\Shopwell\Core\Checkout\Payment\PaymentException::unknownPaymentMethod`. Use `unknownPaymentMethodById` or `unknownPaymentMethodByHandlerIdentifier` instead.
