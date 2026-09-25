---
title: Remove core dependencies from ContextController
issue: NEXT-21967
author: Stefan Sluiter
author_email: s.sluiter@shopwell.com
author_github: ssltg
---
# Core
* Added `Shopwell\Core\Checkout\Customer\SalesChannel\AbstractChangeLanguageRoute`
* Added `Shopwell\Core\Checkout\Customer\SalesChannel\ChangeLanguageRoute`
* Added new store-api route `/account/change-language`
* Changed `\Shopwell\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute::switchContext` to check return the new domain on language change.
* Added `getRedirectUrl` to `Shopwell\Core\System\SalesChannel\ContextTokenResponse` to hold the redirectUrl in a token change if necessary. 
___
# Storefront
* Changed `Shopwell\Storefront\Controller\ContextController` to use `ChangeLanguageRoute` and `ContextSwitchRoute` instead of repositories.
* Deprecated `ChangeLanguageRoute` in `Shopwell\Storefront\Controller\ContextController`. This will be removed in v6.5.0.0.
* Deprecated the automatic change of the customers language on the change of the storefront language in `\Shopwell\Storefront\Controller\ContextController::switchLanguage`