---
title: Use StoreApiException to show proper errors from API
issue: NEXT-15299
author: Tobias Berge
author_email: t.berge@shopwell.com 
author_github: @tobiasberge
---
# Core
* Changed `Shopwell\Core\Framework\Store\Services\ExtensionDownloader` and added `Shopwell\Core\Framework\Store\Services\ExtensionLoader` as argument
* Changed `Shopwell\Core\Framework\Store\Services\ExtensionDownloader::download` and throw `Shopwell\Core\Framework\Store\Exception\StoreApiException` instead of `GuzzleHttp\Exception\ClientException`
___
# Administration
* Changed `Resources/app/administration/.gitignore` and added `test/_mocks_/entity-schema.json`
* Added data prop `installationFailedError` to `Resources/app/administration/src/module/sw-extension/component/sw-extension-card-bought/index.js` in order to store errors from failed installation attempts
* Changed method `downloadExtension` in `Resources/app/administration/src/module/sw-extension/service/extension-store-action.service.js` and pass context `Shopwell.Context.api` to `basicHeaders` call
