---
title: Migrate Error Store to Pinia
issue: NEXT-38621
author: Jannis Leifeld
author_email: j.leifeld@shopwell.com
author_github: @Jannis Leifeld
---
# Administration
* Removed the `error` store written in Vuex (replaced with a Pinia store)
* Removed the `error-store.data.js` file, methods are now used directly in the Pinia store
* Added a new `error` store written in Pinia
___
# Upgrade Information
## "error" Vuex store moved to Pinia

The error store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopwell.Store.get('error')`.

### Before:
```js
Shopwell.State.get('error');
```

### After:
```js
Shopwell.Store.get('error');
```

## Removed Shopwell.Data.ErrorStore

Replace the methods from `Shopwell.Data.ErrorStore` with the methods from the new `error` store.

### Before:
```js
Shopwell.Data.ErrorStore.addApiError(expression, error, state, setReactive);
```

### After:
```js
Shopwell.Store.get('error').addApiError({ expression, error });
```
