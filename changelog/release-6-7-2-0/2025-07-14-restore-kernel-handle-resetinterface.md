---
title: Restore ResetInterface support in long-running runtimes
issue: #11215
author: Mateusz Flasiński
author_email: mateuszflasinski@gmail.com
author_github: @mateuszfl
---

# Core

* Changed `Shopwell\Core\Kernel` to restore support for Symfony's `ResetInterface` by adjusting the `boot()` method and preserving a minimal `handle()` override.
* Removed redundant logic in `Kernel::boot()` that prevented Symfony's reset lifecycle from executing correctly.

___

# Upgrade Information

## Better support for long-running runtimes

If you are running Shopwell in a long-running environment (e.g., FrankenPHP or RoadRunner),
this change enables Symfony to properly reset services implementing `ResetInterface` between requests.
No configuration changes are required.
