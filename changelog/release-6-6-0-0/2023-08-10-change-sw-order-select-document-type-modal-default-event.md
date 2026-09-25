---
title: Change sw-order-select-document-type-modal default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Changed `sw-order-select-document-type-modal` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-order-select-document-type-modal default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
