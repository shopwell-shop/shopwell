---
title: Install assets of apps
issue: NEXT-19583
---
# Core
* Added `\Shopwell\Core\Framework\Plugin\Util\AssetService::copyAssetsFromApp()`-method to copy assets from Apps to the asset filesystem.
* Changed `\Shopwell\Core\Framework\Adapter\Asset\AssetInstallCommand` to also install assets from Apps.
* Changed `\Shopwell\Core\Framework\App\Lifecycle\AppLifecycle` to automatically install assets from App on install/update and delete assets on app delete.
