---
title: Change sw-promotion-v2-rule-select default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Changed `sw-promotion-v2-rule-select` to emit `update:collection` instead of `change`
___
# Next Major Version Changes
## sw-promotion-v2-rule-select default event:
* Change event listeners from `@change="onChange"` to `@update:collection="onChange"`
