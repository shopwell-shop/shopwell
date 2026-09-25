---
title: Add system config webhook
issue: NEXT-26080
---

# Core

* Added new webhook `app.config.changed` to react as app for system config changes.

___

# Next Major Version Changes

* Changed the following classes to be internal:
  - `\Shopwell\Core\Framework\Webhook\Hookable\HookableBusinessEvent`
  - `\Shopwell\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent`
  - `\Shopwell\Core\Framework\Webhook\Hookable\HookableEventFactory`
  - `\Shopwell\Core\Framework\Webhook\Hookable\WriteResultMerger`
  - `\Shopwell\Core\Framework\Webhook\Message\WebhookEventMessage`
  - `\Shopwell\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask`
  - `\Shopwell\Core\Framework\Webhook\BusinessEventEncoder`
  - `\Shopwell\Core\Framework\Webhook\WebhookDispatcher`
