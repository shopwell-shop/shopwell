---
title: Fix update email template type error when missing translation
issue: NEXT-32289
---
# Core
* Changed method `\Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction::updateMailTemplateType` to only trigger warning when mailTemplateTypeTranslation is not exists for given context language id
