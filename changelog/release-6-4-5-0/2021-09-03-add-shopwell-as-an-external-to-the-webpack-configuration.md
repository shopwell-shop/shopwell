---
title: Add Shopwell as an external to the webpack configuration
issue: NEXT-16380
author: Jannis Leifeld
author_email: j.leifeld@shopwell.com 
author_github: Jannis Leifeld
---
# Administration
* Added `Shopwell` to the `externals` in the webpack configuration. This allows to import Shopwell (e.g. `import { Module } from 'Shopwell'`) instead of using the global Shopwell object (e.g. `const { Module } = Shopwell`). When plugins are using this they need to add `Shopwell` to the "paths" in their `jsconfig.json` which redirect do `src/core/shopwell`. It could also lead to an ESLint failure of `import/order` because the import have to placed before the local imports. Then you need to move the import to the top.
