---
title: Reduce downtime while theme change
issue: https://github.com/shopwell-shop/shopwell/issues/7768
author: Michael Telgmann
author_github: @mitelg
---
# Storefront

* Added a database transaction around the calls in `\Shopwell\Storefront\Theme\ThemeService::assignTheme` to ensure the required database changes are committed at the same time to reduce the time the system is in an inconsistent state.
