---
title: Transition shopwellApps store to pinia
issue: NEXT-38637
author: Jannis Leifeld
author_email: j.leifeld@shopwell.com
author_github: @Jannis Leifeld
---

# Administration
* Removed the `shopwellApps` store written in Vuex (replaced with a Pinia store)
* Added a new `shopwellApps` store written in Pinia
___
# Upgrade Information
## "shopwellApps" Vuex store moved to Pinia

The `shopwellApps` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopwell.Store.get('shopwellApps')`.

### Before:
```js
Shopwell.State.get('shopwellApps');
```

### After:
```js
Shopwell.Store.get('shopwellApps');
```

## Removed `setApps` mutation from `shopwellApps` store

The `setApps` mutation has been removed from the `shopwellApps` store. Instead, you can now directly mutate the `shopwellApps` state.

### Before:
```js
Shopwell.State.get('shopwellApps').setApps([ ...theApps ]);
```

### After:
```js
Shopwell.Store.get('shopwellApps').setApps = [ ...theApps ];
```

## Removed `setSelectedIds` mutation from `shopwellApps` store

The `setSelectedIds` mutation has been removed from the `shopwellApps` store. Instead, you can now directly mutate the `shopwellApps` state.

### Before:
```js
Shopwell.State.get('shopwellApps').setSelectedIds([ ...theIds ]);
```

### After:
```js
Shopwell.Store.get('shopwellApps').setSelectedIds = [ ...theIds ];
```
