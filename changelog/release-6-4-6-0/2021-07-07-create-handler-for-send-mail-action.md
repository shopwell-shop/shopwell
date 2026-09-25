---
title: Create handler for send mail action.
issue: NEXT-15154
---
# Core
* Added a new constant `SEND_MAIL` in `Shopwell\Core\Content\Flow\Action\FlowAction`.
* Added `SendMailAction` class at `Shopwell\Core\Content\Flow\Action\FlowAction` which used to send email to customers.
* Added `FlowSendMailActionEvent` class at `Shopwell\Core\Content\Flow\Events\FlowSendMailActionEvent` which used to dispatch an event when `SendMailAction` is called.
* Added `MailAware` interface at `Shopwell\Core\Framework\Event`.
* Deprecated `MailSendSubscriberBridgeEvent` at `Shopwell\Core\Content\MailTemplate\Event\MailSendSubscriberBridgeEvent.php` use `FlowSendMailActionEvent` instead.
* Deprecated `MailSendSubscriber` at `Shopwell\Core\Content\MailTemplate\Event\MailSendSubscriberBridgeEvent.php` use `SendMailAction` instead.
