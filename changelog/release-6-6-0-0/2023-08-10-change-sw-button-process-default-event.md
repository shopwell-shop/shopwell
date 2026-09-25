---
title: Change sw-button-process default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Changed `sw-button-process` to emit `update:processSuccess` instead of `process-finish`
___
# Next Major Version Changes
## sw-button-process default event:
* Change event listeners from `@process-finish="onFinish"` to `@update:processSuccess="onFinish"`
