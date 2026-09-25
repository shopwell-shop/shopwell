---
title: Remove overwrite of CreatedByField with non-live version
issue: 9612
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
author_github: @mstegmeyer
---
# Core
* Changed `Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer` to not write, when it's dealing with a non-live version
___
# Administration
* Deprecated property and update method `createdById` for `sw-order-detail`. It will be removed in 6.8.0.
