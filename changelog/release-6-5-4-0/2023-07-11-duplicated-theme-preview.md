---
title: Fix duplication of theme images
issue: NEXT-25804
---
# Storefront
* Changed `Shopwell\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass` by adding `Shopwell\Storefront\Migration\V6_5` to migration directories.
  * Added `Shopwell\Storefront\Migration\V6_5\Migration1688644407ThemeAddThemeConfig`
    * Added JSON field `theme_json` to table `theme`
* Added for `v6.6.0` method `createFromThemeJson` to `Shopwell\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory`
* Added property `themeJson` and getter and setter to `\Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration`
* Added method `createFromThemeJson` to `Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory` to create a `StorefrontPluginConfiguration` from the json of the theme in the db.
* Added field `themeJson` to `Shopwell\Storefront\Theme\ThemeDefinition`
* Added property `themeJson` to `Shopwell\Storefront\Theme\ThemeEntity`
* Changed `\Shopwell\Storefront\Theme\ThemeLifecycleService` to compare last installed themeJson version with current to prevent duplicated images.
