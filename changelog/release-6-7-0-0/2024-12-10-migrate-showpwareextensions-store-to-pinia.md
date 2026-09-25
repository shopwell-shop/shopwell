---
title: Migrate showpwareExtensions store to Pinia
issue: NEXT-39903
author: Iván Tajes Vidal
author_email: i.tajesvidal@shopwell.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `shopwellExtensions` store written in Vuex (replaced with a Pinia store)
* Added a new `shopwellExtensions` store written in Pinia
___
# Upgrade Information
## "shopwellExtensions" Vuex store moved to Pinia

The `shopwellExtensions` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopwell.Store.get('shopwellExtensions')`.

### Before:
```js
Shopwell.State.get('shopwellExtensions');
```

### After:
```js
Shopwell.Store.get('shopwellExtensions');
```

## Removed `setExtensionListing` mutation from `shopwellExtensions` store

The `setExtensionListing` mutation has been removed from the `shopwellExtensions` store. Instead, you can now directly mutate the `extensionListing` state.

### Before:
```js
Shopwell.State.get('shopwellExtensions').setExtensionListing(extensions);
```

### After:
```js
Shopwell.Store.get('shopwellExtensions').extensionListing = extensions;
```

## Removed `categoriesLanguageId` mutation from `shopwellExtensions` store

The `categoriesLanguageId` mutation has been removed from the `shopwellExtensions` store. Instead, you can now directly mutate the `categoriesLanguageId` state.

### Before:
```js
Shopwell.State.get('shopwellExtensions').categoriesLanguageId(languageId);
```

### After:
```js
Shopwell.Store.get('shopwellExtensions').categoriesLanguageId = languageId;
```

## Removed `setUserInfo` mutation from `shopwellExtensions` store

The `setUserInfo` mutation has been removed from the `shopwellExtensions` store. Instead, you can now directly mutate the `userInfo` state.

### Before:
```js
Shopwell.State.get('shopwellExtensions').setUserInfo(userInfo);
```

### After:
```js
Shopwell.Store.get('shopwellExtensions').userInfo = userInfo;
```

## Removed `myExtensions` mutation from `shopwellExtensions` store

The `myExtensions` mutation has been removed from the `shopwellExtensions` store. Instead, you have to use `setMyExtensions` action. This change is done to avoid conflicts with the state `myExtesions`.

### Before:
```js
Shopwell.State.get('shopwellExtensions').myExtensions(extensions);
```

### After:
```js
Shopwell.State.get('shopwellExtensions').setMyExtensions(extensions);
```
