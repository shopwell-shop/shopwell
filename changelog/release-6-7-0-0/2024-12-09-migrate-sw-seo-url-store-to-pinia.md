---
title: Migrate sw-seo-url store to Pinia
issue: NEXT-39902
author: Iván Tajes Vidal
author_email: i.tajesvidal@shopwell.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `swSeoUrl` store written in Vuex (replaced with a Pinia store)
* Added a new `swSeoUrl` store written in Pinia
___
# Upgrade Information
## "swSeoUrl" Vuex store moved to Pinia.

The `swSeoUrl` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopwell.Store.get('swSeoUrl')`.

### Before:
```js
Shopwell.State.get('swSeoUrl');
```

### After:
```js
Shopwell.Store.get('swSeoUrl');
```

## Removed `setSeoUrlCollection` mutation from `swSeoUrl` store

The `setSeoUrlCollection` mutation has been removed from the `swSeoUrl` store. Instead, you can now directly mutate the `seoUrlCollection` state.

### Before:
```js
Shopwell.State.get('swSeoUrl').setSeoUrlCollection(seoUrlCollection);
```

### After:
```js
Shopwell.Store.get('swSeoUrl').seoUrlCollection = seoUrlCollection;
```

## Removed `setOriginalSeoUrls` mutation from `swSeoUrl` store

The `setOriginalSeoUrls` mutation has been removed from the `swSeoUrl` store. Instead, you can now directly mutate the `originalSeoUrls` state.

### Before:
```js
Shopwell.State.get('swSeoUrl').setOriginalSeoUrls(originalSeoUrls);
```

### After:
```js
Shopwell.Store.get('swSeoUrl').originalSeoUrls = originalSeoUrls;
```

## Removed `setCurrentSeoUrl` mutation from `swSeoUrl` store

The `setCurrentSeoUrl` mutation has been removed from the `swSeoUrl` store. Instead, you can now directly mutate the `currentSeoUrl` state.

### Before:
```js
Shopwell.State.get('swSeoUrl').setCurrentSeoUrl(currentSeoUrl);
```

### After:
```js
Shopwell.Store.get('swSeoUrl').currentSeoUrl = currentSeoUrl;
```

### After:
```js
Shopwell.Store.get('swSeoUrl').originalSeoUrls = originalSeoUrls;
```

## Removed `setDefaultSeoUrl` mutation from `swSeoUrl` store

The `setDefaultSeoUrl` mutation has been removed from the `swSeoUrl` store. Instead, you can now directly mutate the `defaultSeoUrl` state.

### Before:
```js
Shopwell.State.get('swSeoUrl').setDefaultSeoUrl(defaultSeoUrl);
```

### After:
```js
Shopwell.Store.get('swSeoUrl').defaultSeoUrl = defaultSeoUrl;
```

## Removed `setSalesChannelCollection` mutation from `swSeoUrl` store

The `setSalesChannelCollection` mutation has been removed from the `swSeoUrl` store. Instead, you can now directly mutate the `salesChannelCollection` state.

### Before:
```js
Shopwell.State.get('swSeoUrl').setSalesChannelCollection(salesChannelCollection);
```

### After:
```js
Shopwell.Store.get('swSeoUrl').salesChannelCollection = salesChannelCollection;
```

## Removed `isLoading` getter from `swSeoUrl` store

The `isLoading` getter has been removed from the `swSeoUrl` store as it is not accessed or updated anywhere.

## Removed `getNewOrModifiedUrls` getter from `swSeoUrl` store

The `getNewOrModifiedUrls` getter has been removed from the `swSeoUrl` store. Instead, you can now use `newOrModifiedUrls` getter, which returns the value directly instead of a function.

### Before:
```js
const newOrModifiedUrls = Shopwell.State.getters['swSeoUrl/getNewOrModifiedUrls']();
```

### After:
```js
const newOrModifiedUrls = Shopwell.Store.get('swSeoUrl').newOrModifiedUrls;
```
