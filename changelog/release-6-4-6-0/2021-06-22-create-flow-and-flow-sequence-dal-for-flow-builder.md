---
title: Create flow and flow sequence DAL for flow builder
issue: NEXT-15110
---
# Core
* Added two new tables `flow` and `flow_sequence` to stored flow and flow sequence data for Flow Builder.
* Added entities, definition and collection for table `flow` at `Shopwell\Core\Content\Flow`.
* Added entities, definition and collection for table `flow_sequence` at `Shopwell\Core\Content\Flow\Aggregate\FlowSequence`.
* Added OneToMany association between `rule` and `flow_sequence`.
* Added new property `flowSequences` to `Shopwell/Core/Content/Rule/RuleEntity`.
* Deprecated `EventActionRuleDefinition` at `Shopwell\Core\Framework\Event\EventAction\Aggregate\EventActionRule`.
* Deprecated `EventActionSalesChannelDefinition` at `Shopwell\Core\Framework\Event\EventAction\Aggregate\EventActionSalesChannel`.
* Deprecated `EventActionCollection`, `EventActionDefinition`, `EventActionEntity`, `EventActionEvents` and `EventActionSubscriber`, at `Shopwell\Core\Framework\Event\EventAction`.
* Deprecated `eventActions` property in `RuleEntity` and `RuleDefinition` at `Shopwell\Core\Content\Rule`.
* Deprecated `eventActions` property in `SalesChannelEntity` and `SalesChannelDefinition` at `Shopwell\Core\System\SalesChannel`.
