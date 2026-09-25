---
title: Apply domain exceptions
issue: NEXT-28610
---
# Core
* Added new domain exception for payment `Shopwell\Core\Checkout\Payment\PaymentException`
* Added new exception methods `orderNotFound`, `documentNotFound` and `generationError` for `Shopwell\Core\Checkout\Document\DocumentException`
* Added new exception methods `invalidPaymentOrderNotStored` and `orderNotFound` for `Shopwell\Core\Checkout\Cart\CartException`
* Added new exception method `paymentMethodNotAvailable` for `Shopwell\Core\Checkout\Order\OrderException`
* Added new exception method `unknownPaymentMethod` for `Shopwell\Core\Checkout\Customer\CustomerException`
___
# Storefront
* Changed `Shopwell\Storefront\Controller\AccountPaymentController::savePayment` to catch `PaymentException` and forward to payment page with `success = false`.

