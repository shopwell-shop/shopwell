---
title: Consistently generate distinguishable names
issue: NEXT-15331
---
# Core
* Added `Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameGenerator` to generate distinguishable names
* Changed `Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber` to only adding distinguishable names as fallback
* Added `Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer`
* Added `Shopwell\Core\Checkout\Payment\Event\PaymentMethodIndexerEvent`
* Changed `Shopwell\Core\Migration\V6_4\Migration1620733405DistinguishablePaymentMethodName` to trigger new `PaymentMethodIndexer`
