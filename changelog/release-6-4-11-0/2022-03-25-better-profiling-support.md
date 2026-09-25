---
title: Better profiling
issue: NEXT-20696
---
# Core
* Added static class `\Shopwell\Core\Profiling\Profiler` to trace various functions.
* Added `Profiler::trace()` calls to multiple services to get better profiles.
* Added interface `\Shopwell\Core\Profiling\Integration\ProfilerInterface` as abstraction for multiple profilers
* Added `\Shopwell\Core\Profiling\Integration\Datadog` to integrate the Profiler with Datadog.
* Added `\Shopwell\Core\Profiling\Integration\Stopwatch` to integrate the Profiler with the Symfony Debug Toolbar.
* Added `\Shopwell\Core\Profiling\Integration\Tideways` to integrate the Profiler with Tideways.
* Deprecated `\Shopwell\Core\Profiling\Checkout\SalesChannelContextServiceProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
* Deprecated `\Shopwell\Core\Profiling\Entity\EntityAggregatorProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
* Deprecated `\Shopwell\Core\Profiling\Entity\EntitySearcherProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
* Deprecated `\Shopwell\Core\Profiling\Entity\EntityReaderProfiler`, the service will be removed in v6.5.0.0, use the `Profiler` directly in your services.
___
# Upgrade Information
## Better profiling integration
Shopwell now supports better profiling for multiple integrations.
To activate profiling and a specific integration, add the corresponding integration name to the `shopwell.profiler.integrations` parameter in your shopwell.yaml file.
___
# Next Major Version Changes
## New Profiling pattern
Due to a new and better profiling pattern we removed the following services:
* `\Shopwell\Core\Profiling\Checkout\SalesChannelContextServiceProfiler`
* `\Shopwell\Core\Profiling\Entity\EntityAggregatorProfiler`
* `\Shopwell\Core\Profiling\Entity\EntitySearcherProfiler`
* `\Shopwell\Core\Profiling\Entity\EntityReaderProfiler`

You can now use the `Profiler::trace()` function to add custom traces directly from your services.
