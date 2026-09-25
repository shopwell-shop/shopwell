---
title: Improvement performance for trigger flow
issue: NEXT-15742
---
# Core
* Added `FlowIndexer`, `FlowIndexingMessage` and `FlowPayloadUpdater` class at `Shopwell\Core\Content\Flow\DataAbstractionLayer`.
* Added `FlowIndexerEvent` class at `Shopwell\Core\Content\Flow\Events`.
* Added `AbstractFlowLoader` interface and `FlowLoader` class at `Shopwell\Core\Content\Flow`.
* Added `payload` column into table `flow`.
* Added `payload` property into `FlowEntity` and `FlowDefinition` class at `Shopwell\Core\Content\Flow`.
* Added `FlowEvent` class at `Shopwell\Core\Framework\Event`.
* Added `SequenceTree` and `SequenceTreeCollection` classes at `Shopwell\Core\Content\Flow\SequenceTree`.
* Added `StopFlowAction` class at `Shopwell\Core\Content\Flow\Action`.
