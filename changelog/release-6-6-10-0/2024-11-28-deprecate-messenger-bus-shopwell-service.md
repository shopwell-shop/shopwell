---
title: Deprecate messenger.bus.shopwell service
issue: NEXT-39755
---
# Core
* Deprecated the `messenger.bus.shopwell` service. The functionality provided by our decorator has been moved to middleware so you can safely use `messenger.default_bus` instead.
___
# Upgrade Information
## Deprecated `messenger.bus.shopwell` service
Change your usages of `messenger.bus.shopwell` to `messenger.default_bus`. As long as you typed the interface `\Symfony\Component\Messenger\MessageBusInterface`, your code will work as expected.

___
# Next Major Version Changes
## Removed `messenger.bus.shopwell` service
Use `messenger.default_bus` instead.
