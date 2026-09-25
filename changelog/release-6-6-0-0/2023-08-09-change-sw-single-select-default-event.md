---
title: Change sw-single-select default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Changed `sw-single-select` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-single-select default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
