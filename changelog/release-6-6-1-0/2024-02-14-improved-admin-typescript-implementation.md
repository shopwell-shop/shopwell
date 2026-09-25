---
title: Improved admin typescript implementation
issue: NEXT-33740
author: Michel Bade
author_email: m.bade@shopwell.com
author_github: @cyl3x
---
# Administration
* Added correct return type to `ApiService.handleResponse` in `api.service.ts`
* Changed `jsonapi-parser.service` to typescript
* Added global interface `ComponentHelper` for typing `Shopwell.Components.getComponentHelpers()`
* Added global interface `CustomShopwellProperties` for typing additional properties added to `ShopwellClass`
