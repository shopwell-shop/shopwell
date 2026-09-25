---
title: Update FlowExecutor to dispatch Webhook event
issue: NEXT-19012
---
# Core
* Added string field `url` into `Shopwell\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition`
* Added property `url` into `Shopwell\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity`
* Added event `Shopwell\Core\Framework\App\Event\AppFlowActionEvent`
* Added function `updateAppFlowActionWebhooks` into `Shopwell\Core\Framework\App\Lifecycle\Persister\WebhookPersister`
* Added function `updateWebhooksFromArray` into `Shopwell\Core\Framework\App\Lifecycle\Persister\WebhookPersister`
* Added exception `Shopwell\Core\Framework\App\Exception\InvalidAppFlowActionVariableException`
* Added class `Shopwell\Core\Framework\App\FlowAction\AppFlowActionProvider`
* Changed function `updateApp` in `Shopwell\Core\Framework\App\Lifecycle\AppLifecycle` to update webhook when update app
* Changed function `getSubscribedEvents` in `Shopwell\Core\Content\Flow\Indexing\FlowIndexer`.
* Added property `appFlowActionId` into `Shopwell\Core\Content\Flow\Dispatching\Struct\ActionSequence`
* Added parameter `appFlowActionId` into method `Shopwell\Core\Content\Flow\Dispatching\Struct\Sequence::createAction()`
* Changed method `executeAction` in `Shopwell\Core\Content\Flow\Dispatching\FlowExecutor` to dispatcher correct event.
* Changed method `update` in `Shopwell\Core\Content\Flow\Indexing\FlowPayloadUpdater` to add `app_flow_action_id` value to payload of flow.
