---
title: Added a webhook validator to the app system
issue: NEXT-12223
author: Maike Sestendrup
---
# Core
* Added `Shopwell\Core\Framework\Webhook\Hookable\HookableEventCollector`, to collect all hookable events and their required privileges.
* Added `Shopwell\Core\Framework\Webhook\Hookable\HookableVadilator`, to validate the given webhooks and the related permissions in a `manifest.xml` file.
* Added `Shopwell\Core\Framework\App\Manifest\ManifestValidator`, to validate a given `Shopwell\Core\Framework\App\Manifest\Manifest`.
* Added the usage of `Shopwell\Core\Framework\App\Manifest\ManifestValidator` in `Shopwell\Core\Framework\App\Command\VerifyManifestCommand`.
* Changed the arguments for `Shopwell\Core\Framework\App\Command\VerifyManifestCommand`. If no manifest file paths are specified, all `manifest.xml` files in the `development/custom/apps` directory are used.
