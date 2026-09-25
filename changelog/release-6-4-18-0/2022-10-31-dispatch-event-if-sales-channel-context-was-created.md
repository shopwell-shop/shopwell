---
title: Dispatch event if sales channel context was created
issue: NEXT-23355
author: Martin Krzykawski
author_email: m.krzykawski@shopwell.com
---
# Core
* Added `Shopwell\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent` that will be dispatched in `Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService::get` if a `Shopwell\Core\System\SalesChannel\SalesChannelContext` was created.
