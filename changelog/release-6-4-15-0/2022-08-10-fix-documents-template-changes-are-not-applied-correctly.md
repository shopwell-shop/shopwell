---
title: Fix documents template changes are not applied correctly
issue: NEXT-19784
---
# Core
* Changed method `\Shopwell\Core\Checkout\Document\Twig\DocumentTemplateRenderer::render` to resolve view path after dispatching `DocumentTemplateRendererParameterEvent`
* Changed method `\Shopwell\Core\Framework\Framework::getTemplatePriority` to return -1
* Changed method `\Shopwell\Core\System\System::getTemplatePriority` to return -1
* Changed method `\Shopwell\Core\Profiling\Profiling::getTemplatePriority` to return -2
___
# Storefront
* Added new class `\Shopwell\Storefront\Theme\SalesChannelThemeLoader` to load theme of a given sales channel id
* Changed class `\Shopwell\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder` to implement `ResetInterface` and add the reset method to reset internal `$themes` property
___
# Administration
* Changed method `\Shopwell\Administration\Administration::getTemplatePriority` to return -1
___
# Elasticsearch
* Changed method `\Shopwell\Elasticsearch\Elasticsearch::getTemplatePriority` to return -1
