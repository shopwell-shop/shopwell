---
title: Custom field helper
issue: NEXT-25977
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com
---
# Core
* Added helper functions to access and change custom fields in entities:
  * `\Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection::setCustomFields`
  * `\Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait::changeCustomFields`
  * `\Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait::getCustomFieldValues`
