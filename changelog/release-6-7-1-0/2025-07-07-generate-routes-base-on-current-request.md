---
title: Generate routes based on current request
---
# Storefront
* Changed `\Shopwell\Storefront\Framework\Routing\Router` to generate routes based on the current request, instead of main request, thus solving issues when the domain changes between sub requests. 
