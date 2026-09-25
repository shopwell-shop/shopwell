---
title: Replace "modals" Vuex store with Pinia store
issue: NEXT-39387
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `modals` store written in Vuex (replaced with a Pinia store)
* Added a new `modals` store written in Pinia
___
# Upgrade Information
## "modals" Vuex store moved to Pinia

The `modals` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopwell.Store.get('modals')`.

### Before:
```js
Shopwell.State.get('modals');

Shopwell.State.commit('modals/openModal', modalEntry);
Shopwell.State.commit('modals/closeModal', locationId);
```

### After:
```js
Shopwell.Store.get('modals');

Shopwell.Store.get('modals').openModal(modalEntry);
Shopwell.Store.get('modals').closeModal(locationId);
```
