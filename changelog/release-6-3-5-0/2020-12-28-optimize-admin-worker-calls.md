---
title:              Optimize admin worker calls
issue:              NEXT-8112
author:             Oliver Skroblin
author_email:       o.skroblin@shopwell.com
author_github:      @OliverSkroblin
---
# Core
* Added `\Shopwell\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener` to stop the message queue worker when no message is inside the queue.
___
# Administration
* Changed `core/worker/admin-worker.worker.js` to set a timeout when no message handled.  
