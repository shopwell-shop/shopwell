---
title: Add criteria `excludes` property
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `excludes` property with getter & setter to `Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria`
* Changed `Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder` to correctly handle the new criteria `excludes` property
* Changed `Shopwell\Core\System\SalesChannel\Api\ResponseFields` to correctly handle the new criteria `excludes` property
