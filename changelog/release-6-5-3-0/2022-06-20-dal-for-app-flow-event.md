---
title: DAL for App flow event.
issue: NEXT-21814
---
# Core
* Added migration to create new table `app_flow_event`.
* Added entities, definition and collection for table `app_flow_event` at `Shopwell\Core\Framework\App\Aggregate\FlowEvent`.
* Added OneToMany association between `app` and `app_flow_event`.
* Added new property `flowEvents` to `Shopwell\Core\Framework\App\AppEntity`.
* Added OneToMany association between `app_flow_event` and `flow`.
* Added new properties are `appFlowEventId` and `appFlowEvent` to `Shopwell\Core\Content\Flow\FlowEntity`.
