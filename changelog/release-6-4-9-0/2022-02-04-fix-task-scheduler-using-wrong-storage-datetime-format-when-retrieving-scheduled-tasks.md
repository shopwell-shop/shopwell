---
title: Fix task scheduler using wrong storage DateTime format when retrieving scheduled tasks.
issue: NEXT-19989
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `\Shopwell\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler` to use `\Shopwell\Core\Defaults::STORAGE_DATE_TIME_FORMAT` when retrieving scheduled tasks.
