---
title: Optimizing theme config loading
issue: https://github.com/shopwell-shop/shopwell/issues/7766
---
# Core
* Added `\Shopwell\Core\System\Snippet\Event\SnippetsThemeResolveEvent` to remove dependency on `Storefront` components from `SnippetService`
* Changed `\Shopwell\Core\System\Snippet\SnippetService`:
  * Used/non-used themes information is retrieved using `SnippetsThemeResolveEvent`
  * Deprecated `getUnusedThemes` method, replacement will not be provided.
___
# Storefront
* Added new `\Shopwell\Storefront\Theme\ThemeRuntimeConfig` entity and `theme_runtime_config` table to store theme runtime configuration
* Added `\Shopwell\Storefront\Theme\ThemeRuntimeConfigService` to handle theme runtime configurations
* Added `\Shopwell\Storefront\Theme\Subscriber\ThemeSnippetsSubscriber` to collect information about active/non-active themes for snippets functionality
* Changed `\Shopwell\Storefront\Theme\ThemeLifecycleService`, adding optional `$configurationCollection` parameter to the `refreshTheme` method
* Deprecated `\Shopwell\Storefront\Theme\ThemeLifecycleService` to be marked as final in the next major version.
* Changed theme configuration loading in the code, used during storefront rendering, to use the new `\Shopwell\Storefront\Theme\ThemeRuntimeConfigService`:
  * `\Shopwell\Storefront\Theme\ResolvedConfigLoader`
  * `\Shopwell\Storefront\Theme\ThemeScripts`
  * `\Shopwell\Storefront\Theme\ThemeInheritanceBuilder`
  * `\Shopwell\Storefront\Framework\Routing\TemplateDataSubscriber`
* Changed `\Shopwell\Storefront\Theme\CachedResolvedConfigLoaderInvalidator` name to `\Shopwell\Storefront\Theme\ThemeConfigCacheInvalidator`
* Deprecated `\Shopwell\Storefront\Theme\CachedResolvedConfigLoader`, as it is no longer used in the storefront
* Deprecated `\Shopwell\Storefront\Theme\Exception\ThemeAssignmentException`
___
# Upgrade Information

## Theme configuration changes
* Theme configuration used during storefront rendering is now stored in a `theme_runtime_config` table and regenerated on the refresh stage of theme lifecycle.
* The `\Shopwell\Storefront\Theme\CachedResolvedConfigLoader` is now deprecated and will be removed in the next major version. Please update the code that directly uses it to use the `\Shopwell\Storefront\Theme\ResolvedConfigLoader` instead.
* The `\Shopwell\Storefront\Theme\Exception\ThemeAssignmentException` is now deprecated and will be removed in the next major version. Please use `\Shopwell\Storefront\Theme\Exception\ThemeException::themeAssignmentException`.
