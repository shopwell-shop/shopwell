---
title: Enable single order document delivery
issue: NEXT-16681
author: Sebastian Seggewiss
author_email: s.seggewiss@shopwell.com 
author_github: seggewiss
---
# Core
* Deprecated `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_STORNO`
* Added `\Shopwell\Core\Content\MailTemplate\Service\AbstractAttachmentService`
* Added `\Shopwell\Core\Content\MailTemplate\Service\AttachmentService`
* Added MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE`
* Added MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE`
* Added MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE`
* Added MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE`
* Added MailTemplate for MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE`
* Added MailTemplate for MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE`
* Added MailTemplate for MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE`
* Added MailTemplate for MailTemplateType `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE`
___
# API
* Added parameter `documentIds` to route `api.action.mail_template.send`
___
# Administration
* Added `sendMailTemplate` function to `mail.api.service`
___
# Upgrade Information
## Core
* Replace `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_STORNO` with `\Shopwell\Core\Content\MailTemplate\MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE`.
