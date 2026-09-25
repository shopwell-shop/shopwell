---
title: Migrate Context Store to Pinia
issue: NEXT-38620
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Changed the context store has been migrated to Pinia. Change from `Shopwell.State.get('context')` to `Shopwell.Store.get('context')`.
* Removed the `context/setAppConfigVersion` mutation. Assign the `Shopwell.Store.get('context').app.config.version` directly.
* Removed the `context/setLanguageId` mutation. Assign the `Shopwell.Store.get('context').api.languageId` directly.
* Removed the `context/setApiSystemLanguageId` mutation. Assign the `Shopwell.Store.get('context').api.systemLanguageId` directly.
* Removed the `context/setApiLanguage` mutation. Assign the `Shopwell.Store.get('context').api.language` directly.
* Removed the `context/setApiInstallationPath` mutation. Assign the `Shopwell.Store.get('context').api.installationPath` directly.
* Removed the `context/setApiApiPath` mutation. Assign the `Shopwell.Store.get('context').api.apiPath` directly.
* Removed the `context/setApiApiResourcePath` mutation. Assign the `Shopwell.Store.get('context').api.apiResourcePath` directly.
* Removed the `context/setApiAssetsPath` mutation. Assign the `Shopwell.Store.get('context').api.assetsPath` directly.
* Removed the `context/setApiInheritance` mutation. Assign the `Shopwell.Store.get('context').api.inheritance` directly.
* Removed the `context/setApiLiveVersionId` mutation. Assign the `Shopwell.Store.get('context').api.liveVersionId` directly.
* Removed the `context/setAppEnvironment` mutation. Assign the `Shopwell.Store.get('context').app.environment` directly.
* Removed the `context/setAppFallbackLocale` mutation. Assign the `Shopwell.Store.get('context').app.fallbackLocale` directly.
* Removed the `context/addAppValue` mutation. Use the `Shopwell.Store.get('context').addAppValue` action.
* Removed the `context/addApiValue` mutation. Use the `Shopwell.Store.get('context').addApiValue` action.
* Removed the `context/addAppConfigValue` mutation. Use the `Shopwell.Store.get('context').addAppConfigValue` action.
* Removed the `context/setApiLanguageId` mutation. Use the `Shopwell.Store.get('context').setApiLanguageId` action.
* Removed the `context/resetLanguageToDefault` mutation. Use the `Shopwell.Store.get('context').resetLanguageToDefault` action.
* Changed the `Shopwell.State.getters['context/isSystemDefaultLanguage']` getter to `Shopwell.Store.get('context').isSystemDefaultLanguage`.
