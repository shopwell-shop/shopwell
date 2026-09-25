---
title: Fix webhook deactivation on failure
---
# Core
* Changed `\Shopwell\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriber::failed` to correctly increase the error count for the webhook and set it to inactive after the threshold is reached. 
