---
title: remove clone in entity searched event
issue: NEXT-15541
author: OliverSkroblin
author_email: o.skroblin@shopwell.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopwell\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent::__construct`, to not clone the provided context and criteria.

