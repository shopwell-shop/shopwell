---
title: Dispatch event after token has been changed
issue: NEXT-10941
author: Kevin Chen
author_email: kevin.chen@perfecthair.ch
author_github: @maqavelli
---
# Core
*  Added new event `Shopwell\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent` which is dispatched in `Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister` replace method
