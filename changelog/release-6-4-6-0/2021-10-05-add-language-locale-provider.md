---
title: Add LanguageLocaleProvider
issue: NEXT-10606
---
# Core
* Added `\Shopwell\Core\System\Locale\LanguageLocaleCodeProvider` to fetch locales for languageIds.
* Changed `\Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriber` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\Content\Product\Cart\ProductFeatureBuilder` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\Content\ProductExport\Service\ProductExportGenerator` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\Framework\Adapter\Translation\Translator` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\Framework\App\AppLocaleProvider` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\Framework\Store\Services\ExtensionLoader` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Changed `\Shopwell\Core\System\Currency\CurrencyFormatter` to use new `LanguageLocaleCodeProvider`, instead of fetching locales manually.
* Deprecated the protected properties in `\Shopwell\Core\Framework\Context`, because they will be natively typed in the future. If you extend the `Context` class make sure to adhere to type constraints for those properties
___
# Upgrade Information

## Context`s properties will be natively typed
The properties of `\Shopwell\Core\Framework\Context` will be natively typed in the future. 
If you extend the `Context` make sure your implementations adheres to the type constraints for the protected properties.
