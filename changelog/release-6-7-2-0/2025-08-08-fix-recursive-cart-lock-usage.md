---
title: Fix recursive cart lock usage
author: Max Stegmeyer
author_email: m.stegmeyer@shopwell.com
---
# Core
* Changed `Shopwell\Core\Checkout\Cart\CartLocker` to not acquire a lock on recursive calls. This allows e.g. triggered events to use the cart lock without causing a deadlock.
