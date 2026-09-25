---
title: Optimize EntityType
issue: NEXT-24511
author: Jan Matthiesen
author_email: jm@netinventors.de
author_github: jmatthiesen81
---

# Core
* Added more specifying type declaration PHP dockblocks.
* Added class member definition `\Shopwell\Core\Framework\Event\EventData\EntityType::$definitionClass` to improve code quality.
* Changed class constructor `\Shopwell\Core\Framework\Event\EventData\EntityType::__construct` to add the possibility to pass an object of `\Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition` to the constructor.
