---
title: Refactoring FlowEvent for implementing the flow DelayAction in Flow Builder
issue: NEXT-22263
---
# Core
* Added `StorableFlow` class in `Shopwell\Core\Content\Flow\Dispatching` to implement the flow DelayAction in FlowBuilder.
* Changed the `dispatch`, `callFlowExecutor` methods in `Shopwell\Core\Content\Flow\Dispatching\FlowDispatcher`, use the `StorableFlow` instead of the original events.
* Changed the `execute`, `executeSequence`, `executeIf`, `executeAction`, `executeSequence` methods in `Shopwell\Core\Content\Flow\Dispatching\FlowExecutor`, use the `StorableFlow` instead of `FlowState` or `FlowEventAware`.
* Added new `FlowFactory` class in `Shopwell\Core\Content\Flow\Dispatching` to create and restore the `StorableFlow`.
* Added new awareness interfaces:
  `Shopwell\Core\Content\Flow\Dispatching\Aware\ConfirmUrlAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\ContactFormDataAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\ContentsAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\ContextTokenAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\CustomerRecoveryAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\DataAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\EmailAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\MessageAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\NameAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\RecipientsAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\ResetUrlAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\ShopNameAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\SubjectAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\TemplateDataAware`.
  `Shopwell\Core\Content\Flow\Dispatching\Aware\UrlAware`.
* Added new classes storer to store the representation of available data and restore the available data for `StorableFlow` from the original events in  `Shopwell\Core\Content\Flow\Dispatching\Storer`:
  `Shopwell\Core\Content\Flow\Dispatching\Storer\ConfirmUrlStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\ContactFormDataStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\ContentsStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\ContextTokenStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\CustomerGroupStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\CustomerRecoveryStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\CustomerStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\DataStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\EmailStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\MessageStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\NameStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\NewsletterRecipientStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\OrderStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\RecipientsStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\ResetUrlStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\ShopNameStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\SubjectStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\TemplateDataStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\UrlStorer`.
  `Shopwell\Core\Content\Flow\Dispatching\Storer\UserStorer`.
* Added index-key for the flow actions services tags.
* Changed all the flow builder actions in `Shopwell\Core\Content\Flow\Dispatching\Action` from event subscriber to tagged services.
* Deprecated the `handle` functions in all the flow builder actions in `Shopwell\Core\Content\Flow\Dispatching\Action`, use the function `handleFlow` instead.

___
# Next Major Version Changes
* In the next major, the flow actions are not executed over the symfony events anymore, we'll remove the dependence from `EventSubscriberInterface` in `Shopwell\Core\Content\Flow\Dispatching\Action\FlowAction`.
that means, all the flow actions extends from `FlowAction` are become the services tag. 
* The flow builder will execute the actions via call directly the `handleFlow` function instead `dispatch` an action event.
* To get an action service in flow builder, we need define the tag action service with a unique key, that key should be an action name.
* About the data we'll use in the flow actions, the data will be store in the `StorableFlow $flow`, use `$flow->getStore('order_id')` or `$flow->getData('order')` instead of `$flowEvent->getOrder`.
  * Use `$flow->getStore($key)` if you want to get the data from aware interfaces. E.g: `order_id` in `OrderAware`, `customer_id` from `CustomerAware` and so on.
  * Use `$flow->getData($key)` if you want to get the data from original events or additional data. E.g: `order`, `customer`, `contactFormData` and so on.

**before**
```xml
 <service id="Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction">
    ...
    <tag name="flow.action"/>
</service>
```

```php
class FlowExecutor
{
    ...
    
    $this->dispatcher->dispatch($flowEvent, $actionname);
    
    ...
}

abstract class FlowAction implements EventSubscriberInterface
{
    ...
}

class SendMailAction extends FlowAction
{
    ...
    public static function getSubscribedEvents()
    {
        return ['action.name' => 'handle'];
    }
    
    public function handle(FlowEvent $event)
    {
        ...
        
        $orderId = $event->getOrderId();
        
        $contactFormData = $event->getConta();
        
        ...
    }
}
```

**after**
```xml
 <service id="Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction">
    ...
    <tag name="flow.action" key="action.mail.send" />
</service>
```

```php
class FlowExecutor
{
    ...
    
    $actionService = $actions[$actionName];
    
    $actionService->handleFlow($storableFlow);
    
    ...
}

abstract class FlowAction
{
    ...
}

class SendMailAction extends FlowAction
{
    ...
    // The `getSubscribedEvents` function has been removed.
    
    public function handleFlow(StorableFlow $flow)
    {
        ...
        
        $orderId = $flow->getStore('order_id');
        
        $contactFormData = $event->getData('contactFormData');
        
        ...
    }
}
```
