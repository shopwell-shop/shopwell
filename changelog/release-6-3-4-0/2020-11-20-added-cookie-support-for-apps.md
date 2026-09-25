---
title: Added cookie consent support for apps
issue: NEXT-12094
---
# Core
* Added `cookies`-field to `manifest-1.0.xsd`, `\Shopwell\Core\Framework\App\Manifest\Manifest` and `\Shopwell\Core\Framework\App\AppDefinition`, to allow app manufacturer to add custom cookies to the cookie consent manager.
___
# Storefront
* Added `\Shopwell\Storefront\Framework\Cookie\AppCookieProvider` to load cookie groups provided in apps.
