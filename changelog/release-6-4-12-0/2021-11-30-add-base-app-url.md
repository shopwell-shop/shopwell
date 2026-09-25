---
title: Added BaseAppUrl to apps and plugins as entry point for AdminExtensionAPI
issue: NEXT-18117
---
# Core
* Added `<base-app-url>` field to admin section of `manifest-1.0.xsd`, to specify the entry point url for the AdminExtensionAPI.
* Changed `\Shopwell\Core\Framework\App\Manifest\Xml\Admin` to parse new base-app-url field.
* Added `baseAppUrl` to `\Shopwell\Core\Framework\App\AppDefinition`.
* Changed `\Shopwell\Core\Framework\App\Lifecycle\AppLifecycle` to handle the base-app-url field.
* Added `\Shopwell\Core\Framework\Plugin::getAdminBaseUrl()`-method, to specify the entry point url for the AdminExtensionAPI.
* Changed `\Shopwell\Core\Framework\Api\Controller\InfoController::getBundles()` to also return the `baseUrl` of the active apps and plugins.
