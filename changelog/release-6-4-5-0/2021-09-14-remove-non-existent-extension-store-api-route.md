---
title: Remove non-existent Extension Store API route
issue: NEXT-16986
author: Frederik Schmitt
author_email: f.schmitt@shopwell.com 
author_github: fschmtt
---
# Core
* Deprecated internal `Shopwell\Core\Framework\Store\Services\StoreClient::getLicenses()`
* Deprecated internal `Shopwell\Core\Framework\Store\Api\StoreController::getLicenseList()`
___
# API
* Deprecated internal route `api.custom.store.licenses`
___
# Administration
* Deprecated internal `getLicenseList()` in `src/core/service/api/store.api.service.js`
