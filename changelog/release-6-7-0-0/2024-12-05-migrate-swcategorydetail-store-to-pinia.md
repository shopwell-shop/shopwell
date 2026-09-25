---
title: Migrate swCategoryDetail store to Pinia
issue: NEXT-39901
author: Iván Tajes Vidal
author_email: i.tajesvidal@shopwell.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `swCategoryDetail` store written in Vuex (replaced with a Pinia store)
* Added a new `swCategoryDetail` store written in Pinia
___
# Upgrade Information
## "shopwellApps" Vuex store moved to Pinia

The `swCategoryDetail` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopwell.Store.get('swCategoryDetail')`.

### Before:
```js
Shopwell.State.get('swCategoryDetail');
```

### After:
```js
Shopwell.Store.get('swCategoryDetail');
```

## Removed `setActiveLandingPage` mutation from `swCategoryDetail` store

The `setActiveLandingPage` mutation has been removed from the `swCategoryDetail` store. Instead, you can now directly mutate the `landingPage` state.

### Before:
```js
Shopwell.State.get('swCategoryDetail').setActiveLandingPage({ landingPage });
```

### After:
```js
Shopwell.Store.get('swCategoryDetail').landingPage = landingPage;
```

## Removed `setActiveCategory` mutation from `swCategoryDetail` store

The `setActiveCategory` mutation has been removed from the `swCategoryDetail` store. Instead, you can now directly mutate the `category` state.

### Before:
```js
Shopwell.State.get('swCategoryDetail').setActiveCategory({ category });
```

### After:
```js
Shopwell.Store.get('swCategoryDetail').category = category;
```

## Removed `setCustomFieldSets` mutation from `swCategoryDetail` store

The `setCustomFieldSets` mutation has been removed from the `swCategoryDetail` store. Instead, you can now directly mutate the `customFieldSets` state.

### Before:
```js
Shopwell.State.get('swCategoryDetail').setCustomFieldSets(newCustomFieldSets);
```

### After:
```js
Shopwell.Store.get('swCategoryDetail').customFieldSets = newCustomFieldSets;
```

## Removed `setLandingPagesToDelete` mutation from `swCategoryDetail` store

The `setLandingPagesToDelete` mutation has been removed from the `swCategoryDetail` store. Instead, you can now directly mutate the `landingPagesToDelete` state.

### Before:
```js
Shopwell.State.get('swCategoryDetail').setLandingPagesToDelete({ landingPagesToDelete });
```

### After:
```js
Shopwell.Store.get('swCategoryDetail').landingPagesToDelete = landingPagesToDelete;
```

## Removed `setCategoriesToDelete` mutation from `categoriesToDelete` store

The `setCategoriesToDelete` mutation has been removed from the `swCategoryDetail` store. Instead, you can now directly mutate the `categoriesToDelete` state.

### Before:
```js
Shopwell.State.get('swCategoryDetail').setCategoriesToDelete({ categoriesToDelete });
```

### After:
```js
Shopwell.Store.get('swCategoryDetail').categoriesToDelete = categoriesToDelete;
```

## Removed `setActiveLandingPage` action from `swCategoryDetail` store

The `setActiveLandingPage` action has been removed from the `swCategoryDetail` store. Instead, you can now directly mutate the `landingPage` state.

### Before:
```js
Shopwell.State.get('swCategoryDetail').setActiveLandingPage({ landingPage });
```

### After:
```js
Shopwell.Store.get('swCategoryDetail').landingPage = landingPage;
```

## Removed `setActiveCategory` action from `swCategoryDetail` store

The `setActiveCategory` action has been removed from the `swCategoryDetail` store. Instead, you can now directly mutate the `category` state.

### Before:
```js
Shopwell.State.get('swCategoryDetail').setActiveCategory({ category });
```

### After:
```js
Shopwell.Store.get('swCategoryDetail').category = category;
```
