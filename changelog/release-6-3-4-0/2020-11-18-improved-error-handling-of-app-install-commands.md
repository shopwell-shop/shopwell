---
title: Improved error handling for app lifecycle commands
issue: NEXT-12229
---
# Core
* Changed `\Shopwell\Core\Framework\App\Lifecycle\AppLifecycle::install()`-method to throw `\Shopwell\Core\Framework\App\Exception\AppAlreadyInstalledException` if the app is already installed.
* Changed `\Shopwell\Core\Framework\App\Command\InstallAppCommand` to catch AppAlreadyInstalledException and report that to the user.
* Changed `\Shopwell\Core\Framework\App\Command\RefreshAppCommand` to print the reason for install or update failures.
* Deprecated `\Shopwell\Core\Framework\App\Lifecycle\AppLifecycleIterator::iterate()`-method, use `\Shopwell\Core\Framework\App\Lifecycle\AppLifecycleIterator::iterateOverApps()` instead. 
* Deprecated `\Shopwell\Core\Framework\App\AppService::refreshApps()`-method, use `\Shopwell\Core\Framework\App\AppService::doRefreshApps()` instead. 
