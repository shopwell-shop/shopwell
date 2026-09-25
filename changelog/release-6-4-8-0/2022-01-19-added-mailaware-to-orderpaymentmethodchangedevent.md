---
title: Added MailAware to OrderPaymentMethodChangedEvent
issue: NEXT-19674
author: PuetzD
author_github: PuetzD
---
# Core
* Added `Shopwell\Core\Framework\Event\MailAware` to `Shopwell\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent` to enable the FlowBuilder to send emails triggered by the OrderPaymentMethodChangedEvent
