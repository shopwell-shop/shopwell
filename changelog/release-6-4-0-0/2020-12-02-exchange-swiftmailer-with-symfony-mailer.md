---
title:              Exchange SwiftMailer with Symfony mailer
issue:              NEXT-12246
author:             Stefan Sluiter
author_email:       s.sluiter@shopwell.com
author_github:      @ssltg
---
# Core
* Added `symfony/mailer ~4.4` to composer.json
* Added `Shopwell\Core\Content\Mail\Service\MailSender`
* Added `Shopwell\Core\Content\Mail\Service\MailService`
* Added `Shopwell\Core\Content\Mail\Service\MailerTransportFactory`
* Added `Shopwell\Core\Content\Mail\Service\AbstractMailSender`
* Added `Shopwell\Core\Content\Mail\Service\AbstractMailService`
* Added `Shopwell\Core\Framework\Feature\Exception\FeatureActiveException`
* Added argument `emailService` with type `Shopwell\Core\Content\Mail\Service\AbstractMailService` in `Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriber`

* Changed argument type of argument `$message` in `Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent` from `Swift_Message` to `Symfony\Component\Mime\Email`
* Changed return type of method `getMessage` in `Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent` from `Swift_Message` to `Symfony\Component\Mime\Email`
* Changed `Shopwell\Core\Framework\Feature\FeatureNotActiveException` to `Shopwell\Core\Framework\Feature\Exception\FeatureNotActiveException`

* Removed `Shopwell\Core\Content\MailTemplate\Service\MailSender`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MailSenderInterface`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MailService`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MailServiceInterface`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MessageFactoryInterface`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MessageFactory`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MessageTransportFactoryInterface`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MessageTransportFactory`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MailerTransportFactory`
* Removed `Shopwell\Core\Content\MailTemplate\Service\MailerTransportFactoryInterface`
* Removed argument `mailService` in `Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriber`
* Removed method `createMessage` in `Shopwell\Core\Content\MailTemplate\Service\MessageFactory` use `createMail` instead
___
# Administration
* Removed block `sw_settings_mailer_smtp_authentication`
* Removed method `authenticationOptions` in component `sw-settings-mailer-smtp`

