---
title: Fix reset active apps after app deactivation
issue: #11515
---
# Core
* Changed `\Shopwell\Core\Framework\App\AppStateService::deactivateApp()` to reset the active apps loader only after the app was marked as inactive in the DB, otherwise this lead to errors where the deactivated app was still in the list of active apps.
