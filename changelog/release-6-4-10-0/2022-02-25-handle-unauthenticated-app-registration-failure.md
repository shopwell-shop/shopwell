---
title: Handle unauthenticated app registration failure
issue: NEXT-20097
author: Frederik Schmitt
author_email: f.schmitt@shopwell.com
author_github: fschmtt
---
# Core
* Changed `Shopwell\Core\Framework\App\Exception\AppRegistrationException` to extend `Shopwell\Core\Framework\ShopwellHttpException`
* Added `Shopwell\Core\Framework\App\Exception\AppLicenseCouldNotBeVerifiedException`
* Changed `Shopwell\Core\Framework\App\Lifecycle\Registration\StoreHandshake::signPayload()` to throw `Shopwell\Core\Framework\App\Exception\AppLicenseCouldNotBeVerifiedException`
___
# Administration
* Changed `src/module/sw-extension/service/index.js` to pass error codes to the `ExtensionErrorService` constructor
* Changed `src/module/sw-extension/service/extension-error.service.js` to handle `actions` and `autoClose` correctly for notifications
