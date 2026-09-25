---
title: Hide recovery url in log
issue: NEXT-24679
---
# Core
* Added a new variable `%shopwell.logger.exclude_events%` in `shopwell.yaml`
* Added new log handler class `\\Shopwell\Core\Framework\Log\Monolog\ExcludeFlowEventHandler` to exclude recovery password events and theirs according mail events from being logged if it's included in `%shopwell.logger.exclude_events%` list
* Changed method `\Shopwell\Core\Content\Flow\Dispatching\Action\handleFlow` to add flow's `eventName` into the `templateData` variable
* Changed class `\Shopwell\Core\Content\MailTemplate\Service\Event\MailErrorEvent` to add the private property `eventName` in the constructor parameter
* Changed class `\Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent` to add the private property `eventName` in the constructor parameter
* Changed class `\Shopwell\Core\Content\MailTemplate\Service\Event\MailSentEvent` to add the private property `eventName` in the constructor parameter
* Changed method `\Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent::getLogData` to add the `eventName` in log data
* Changed method `\Shopwell\Core\Content\MailTemplate\Service\Event\MailErrorEvent::getLogData` to add the `eventName` in log data
* Changed method `\Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent::getLogData` to add the `eventName` in log data
* Changed method `\Shopwell\Core\Content\MailTemplate\Service\Event\MailSentEvent::getLogData` to add the `eventName` in log data
* Added a new migration in `\Shopwell\Core\Migration\V6_4\Migration1672164687FixTypoInUserRecoveryPasswordResetMail` to fix a typo in user recovery request mail template
* Changed `Shopwell\Core\Content\Mail\Service\MailService` to inject `logger` into the service to log errors when they're thrown
