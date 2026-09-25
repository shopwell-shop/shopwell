---
title: Replace Vuex with Pinia
issue: NEXT-36700
author: Sebastian Seggewiss
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Added `Shopwell.Store` (Pinia) implementation
* Changed everything `Shopwell.State` related to deprecated state
___
# Upgrade Information
## Transition Vuex states into Pinia Stores
1. In Pinia, there are no `mutations`. Place every mutation under `actions`.
2. `state` needs to be an arrow function returning an object: `state: () => ({})`.
3. `actions` and `getters` no longer need to use the `state` as an argument. They can access everything with correct type support via `this`.
4. Use `Shopwell.Store.register` instead of `Shopwell.State.registerModule`.
5. Use `Shopwell.Store.unregister` instead of `Shopwell.State.unregisterModule`.
6. Use `Shopwell.Store.list` instead of `Shopwell.State.list`.
7. Use `Shopwell.Store.get` instead of `Shopwell.State.get`.
___
# Next Major Version Changes
## All Vuex stores will be transitioned to Pinia
* All Shopwell states will become Pinia Stores and will be available via `Shopwell.Store`
