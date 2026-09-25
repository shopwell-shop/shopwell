---
title: Fixed theme config inheritance for database child themes
issue: #8591
---
# Storefront
* Changed method `getConfigInheritance` in `\Shopwell\Storefront\Theme\ConfigLoader\DatabaseConfigLoader` and `\Shopwell\Storefront\Theme\ThemeMergedConfigBuilder` to properly resolve original parent theme config inheritance.