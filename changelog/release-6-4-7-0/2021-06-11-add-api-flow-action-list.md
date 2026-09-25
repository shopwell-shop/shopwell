---
title: Add api flow action list
issue: NEXT-15558
---
# Core
* Added new API `GET: /api/_info/actions.json` at `Shopwell\Core\Framework\Api\Controller\InfoController` which used to return lists flow action.
* Added `FlowActionCollector`, classes at `Shopwell\Core\Content\Flow\Action`.
* Added `FlowActionCollectorResponse` class at `Shopwell\Core\Content\Flow\Action`.
* Added `FlowActionDefinition` class at `Shopwell\Core\Content\Flow\Action`
* Added `AddTagAction`, `RemoveTagAction`, and `SetOrderStateAction` class at `Shopwell\Core\Content\Flow\Action`.
* Removed `AddOrderTagAction` class at `Shopwell\Core\Content\Flow\Action`.
* Added `FlowActionCollectorEvent` to dispatch when have a collect flow action.
* Added `UserAware` interfaces at `Shopwell\Core\Framework\Event`.
* Added `orderAware`, `customerAware`, `webhookAware`, `userAware` properties and getter, setter for them into class `BusinessEventDefinition` at `Shopwell\Core\Framework\Event`.
* Added `getCustomerId` function into `CustomerAccountRecoverRequestEvent`, `CustomerChangedPaymentMethodEvent`, `CustomerDeletedEvent`, `CustomerDoubleOptInRegistrationEvent`, `CustomerGroupRegistrationAccepted`, `CustomerGroupRegistrationDeclined`, `CustomerLoginEvent`, `CustomerLogoutEvent`, `CustomerRegisterEvent` and `DoubleOptInGuestOrderEvent` at `Shopwell\Core\Checkout\Customer\Event`.
* Added `getOrderId` function into `OrderStateMachineStateChangeEvent` at `Shopwell\Core\Checkout\Order\Event`.
* Added `getUserId` function into `UserRecoveryRequestEvent` at `Shopwell\Core\System\User\Recovery`.
