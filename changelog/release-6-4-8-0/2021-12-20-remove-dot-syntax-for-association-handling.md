---
title: Remove dot (`.`) syntax handling for associations
issue: NEXT-19034
---
# Core
* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::addAssociationFieldsToCriteria()` and removed obsolete code for dot syntax inside associations, which is not supported.
