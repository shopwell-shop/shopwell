---
title: Pass request data to app pay and finalize call
issue: NEXT-18711
author: Sebastian Seggewiss
author_email: s.seggewiss@shopwell.com 
author_github: seggewiss
---
# Core
* Changed `Shopwell\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler::pay` to pass request data to `Shopwell\Core\Framework\App\Payment\Payload\Struct\AsyncPayPayload`
* Changed `Shopwell\Core\Framework\App\Payment\Payload\Struct\AsyncPayPayload` to take request data
* Changed `Shopwell\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler::finalize` to pass request query parameters to `Shopwell\Core\Framework\App\Payment\Payload\Struct\AsyncFinalizePayload`
* Changed `Shopwell\Core\Framework\App\Payment\Payload\Struct\AsyncFinalizePayload` to take request query parameters
