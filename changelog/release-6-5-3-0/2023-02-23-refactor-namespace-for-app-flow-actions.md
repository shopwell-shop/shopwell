---
title: Refactor namespace for app flow actions
issue: NEXT-25362
---
# Core
* Changed namespace from `Shopwell\Core\Framework\App\FlowAction` to `Shopwell\Core\Framework\App\Flow\Action` for classes bellow:
  * FlowAction.php
  * AppFlowActionProvider.php
  * AppFlowActionLoadedSubscriber.php
* Changed namespace from `Shopwell\Core\Framework\App\FlowAction\Xml` to `Shopwell\Core\Framework\App\Flow\Action\Xml` for classes bellow:
  * Action.php
  * Actions.php
  * Config.php
  * Headers.php
  * InputField.php
  * Metadata.php
  * Parameter.php
  * Parameters.php
* Deprecated class `Shopwell\Core\Framework\App\FlowAction\AppFlowActionProvider` use `Shopwell\Core\Framework\App\Flow\FlowAction\AppFlowActionProvider` instead.
* Deprecated `Shopwell\Core\Framework\App\FlowAction\Schema\flow-action-1.0.xsd` use `Shopwell\Core\Framework\App\Flow\Schema\flow-1.0.xsd` instead.
___
# Upgrade Information
## The app custom trigger and the app action can be defined in one xml file.
Since v6.5.2.0, we can define the flow custom trigger and the flow app action in one XML file.
To do that, we add the `Shopwell\Core\Framework\App\Flow\Schema\flow-1.0.xsd` to support defining both of them.

* ***Example***
```xml
<flow-extensions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="flow-1.0.xsd">
    <flow-events>
        <flow-event>...</flow-event>
    </flow-events>
    <flow-actions>
        <flow-action>...</flow-action>
    </flow-actions>
</flow-extensions>
```
___
# Next Major Version Changes
## Removal of `flow-action-1.0.xsd`
We removed `Shopwell\Core\Framework\App\FlowAction\Schema\flow-action-1.0.xsd`, use `Shopwell\Core\Framework\App\Flow\Schema\flow-1.0.xsd` instead.
## Removal of `Shopwell\Core\Framework\App\FlowAction` and `Shopwell\Core\Framework\App\FlowAction\Xml`
We moved all class from namespaces `Shopwell\Core\Framework\App\FlowAction` to `Shopwell\Core\Framework\App\Flow\Action` and `Shopwell\Core\Framework\App\FlowAction\Xml` to `Shopwell\Core\Framework\App\Flow\Action\Xml`.
Please use new namespaces.
