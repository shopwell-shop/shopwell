---
title: make the FirstRunWizardClient depend less on StoreService
issue: NEXT-18846
author: Adrian Les
author_email: a.les@shopwell.com
author_github: adrianles
---
# Core
* Added `Shopwell\Core\Framework\Store\Services\TrackingEventClient`
* Changed `Shopwell\Core\Framework\Store\Services\FirstRunWizardClient` to use `Shopwell\Core\Framework\Store\Services\TrackingEventClient`
* Deprecated `Shopwell\Core\Framework\Store\Services\StoreService::fireTrackingEvent()`
* Deprecated `Shopwell\Core\Framework\Store\Services\StoreService::getLanguageByContext()`
