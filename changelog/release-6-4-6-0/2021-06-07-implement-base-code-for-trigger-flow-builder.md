---
title: Implement base code for trigger flow builder
issue: NEXT-15107
---
# Core
* Added `FlowExecutor` and `FlowState` classes at `Shopwell\Core\Content\Flow`.
* Added `FlowDispatcher` class at `Shopwell\Core\Content\Flow` to dispatch business event for Flow Builder.
* Added `AddOrderTagAction` class at `Shopwell\Core\Content\Flow\Action`.
* Added `FlowAction` abstract class at `Shopwell\Core\Content\Flow\Action`.
* Added `CustomerAware` and `OrderAware` interfaces at `Shopwell\Core\Framework\Event`.
* Added function `getOrderId` into `Shopwell\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent`.
* Deprecated `BusinessEventDispatcher` at `Shopwell\Core\Framework\Event` which will be removed in v6.5.0.
* Added 'display_group' column into `flow_sequence` table.
* Added 'displayGroup' property into `FlowSequenceEntity` and `FlowSequenceDefinition` at `Shopwell\Core\Content\Flow\Aggregate\FlowSequence`.
* Added `Sequence` class at `Shopwell\Core\Content\Flow\SequenceTree`.
