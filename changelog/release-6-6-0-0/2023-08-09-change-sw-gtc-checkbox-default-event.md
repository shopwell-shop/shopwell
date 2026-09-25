---
title: Change sw-gtc-checkbox default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Changed `sw-gtc-checkbox` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-gtc-checkbox default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
