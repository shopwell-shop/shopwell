---
title: Remove deprecated autoload === true at associations
issue: NEXT-25327
---
# Core
* Changed some files to remove deprecated autoload === true
  * `Shopwell\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition`
  * `Shopwell\Core\Checkout\Order\OrderDefinition`
  * `Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition`
  * `Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition`
  * `Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition`
  * `Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition`
* Changed `Shopwell\Core\Checkout\Cart\Order\Api\OrderConverterController::convertToCart` to load order with the association `transactions.stateMachineState`.
* Changed `Shopwell\Core\Checkout\Cart\Order\RecalculationService` to load order with the association `transactions.stateMachineState`.
* Changed `Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRoute::order` to load the order with the associations `stateMachineState`, `transactions.stateMachineState`, `deliveries.stateMachineState`.
* Changed `Shopwell\Core\Checkout\Order\Listener\OrderStateChangeEventListener::onOrderDeliveryStateChange` to load order with the association `order.transactions.stateMachineState`.
* Changed `Shopwell\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute::setPayment` to load order with the associations `stateMachineState`, `transactions.stateMachineState`, `deliveries.stateMachineState`.
* Changed `Shopwell\Core\Content\Flow\Dispatching\Storer\OrderStorer::load` to load order with the associations `stateMachineState`, `transactions.stateMachineState`, `deliveries.stateMachineState`.
___
# Administration
* Changed computed `orderCriteria` in `sw-order-detail` component to add other associations no longer autoload.
* Changed computed `orderCriteria` in `sw-order-list` component to add other associations no longer autoload.
___
# Storefront
* Changed `Shopwell\Storefront\Page\Account\Order\AccountEditOrderPageLoader::load` to load order with the associations `stateMachineState`, `transactions.stateMachineState`, `deliveries.stateMachineState`.
___
# Upgrade Information
If you are relying on these associations:
 `order.stateMachineState`
 `order_transaction.stateMachineState`
 `order_delivery.stateMachineState`
 `order_delivery.shippingOrderAddress`
 `order_transaction_capture.stateMachineState`
 `order_transaction_capture_refund.stateMachineState`
 `tax_rule.type`
please associate the definitions directly with the criteria because we will remove autoload from version 6.6.0.0.
