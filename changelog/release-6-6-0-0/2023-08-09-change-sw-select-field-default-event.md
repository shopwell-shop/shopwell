---
title: Change sw-select-field default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopwell.com
author_github: @seggewiss
---
# Administration
* Changed `sw-select-field` default event from `change` to `update:value`
___
# Next Major Version Changes
## Breaking Change 1:
* Change `sw-select-field` change listeners `@change="onChange"` to `@update:value="onChange"`
