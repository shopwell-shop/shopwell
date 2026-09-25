---
title: Unwrap messages when routing
issue: NEXT-39749
---
# Core
* Changed `\Shopwell\Core\Framework\MessageQueue\Middleware\RoutingOverwriteMiddleware::getTransports` to unwrap Envelope message so that they are correctly routed
