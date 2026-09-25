---
title: Add event for health check
issue: NEXT-32336
author: Silvio Kennecke
author_email: development@silvio-kennecke.de
author_github: @silviokennecke
---
# Administration
* Changed `Shopwell\Core\Framework\Api\Controller\HealthCheckController` to dispatch a `Shopwell\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent` 
