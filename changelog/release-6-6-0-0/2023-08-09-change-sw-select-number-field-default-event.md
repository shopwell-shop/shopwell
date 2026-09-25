---
title: Change sw-select-number-field default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Changed `sw-select-number-field` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-select-number-field default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
