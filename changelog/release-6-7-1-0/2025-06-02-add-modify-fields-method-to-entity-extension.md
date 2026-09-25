---
title: Add modifyFields method to EntityExtension
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: gecolay
---
# Core
* Added `modifyFields` to `Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension` (Allows to modify fields of an entity)
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition` to call the new `modifyFields` method
