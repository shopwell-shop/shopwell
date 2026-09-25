---
title: Use admin interface language for extension information
issue: NEXT-15586
author: Tobias Berge
author_email: t.berge@shopwell.com 
author_github: @tobiasberge
---
# Core
* Added argument `Shopwell\Core\Framework\Store\Services\StoreService` to `Shopwell\Core\Framework\Store\Services\ExtensionLoader`
* Added argument `user.repository` to `Shopwell\Core\Framework\Store\Api\ExtensionStoreDataController`
* Added argument `language.repository` to `Shopwell\Core\Framework\Store\Api\ExtensionStoreDataController`
* Added new method `switchContext` to `Shopwell\Core\Framework\Store\Api\ExtensionStoreDataController` in order to use the current admin language for the current context when running method `getInstalledExtensions`
* Added argument `Shopwell\Core\Framework\Store\Services\StoreService` to `Shopwell\Core\Framework\Store\Services\ExtensionLoader`
* Added new optional argument `(string) $locale` to method `loadFromArray` in `Shopwell\Core\Framework\Store\Services\ExtensionLoader`
