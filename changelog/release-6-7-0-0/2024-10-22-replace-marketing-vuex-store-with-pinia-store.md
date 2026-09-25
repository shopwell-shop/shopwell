---
title: Replace "marketing" Vuex store with Pinia store
issue: NEXT-38629
author: Jannis Leifeld
author_email: j.leifeld@shopwell.com
author_github: @Jannis Leifeld
---
# Administration
* Removed the `marketing` store written in Vuex (replaced with a Pinia store)
* Added a new `marketing` store written in Pinia
___
# Upgrade Information
## "marketing" Vuex store moved to Pinia

The marketing store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopwell.Store.get('marketing')`.

### Before:
```js
Shopwell.State.get('marketing');

Shopwell.State.commit('marketing/setCampaign', campaign);
```

### After:
```js
Shopwell.Store.get('marketing');

Shopwell.Store.get('marketing').setCampaign(campaign);
```
