---
title: Simplify emitting metrics
issue: NEXT-37689
flag: TELEMETRY_METRICS
---

# Core
* Removed `Shopwell\Core\Framework\Telemetry\Metrics\MetricEventDispatcher`
* Removed the ability to configure Metrics via Attributes
* Added `Shopwell\Core\Framework\Telemetry\Metrics\Config\MetricConfiguration` concept to configure metrics
* Added `Shopwell\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface` that enables configuring metric transports via `Shopwell\Core\Framework\Telemetry\Metrics\Config\TransportConfig` 
* Changed Metric types with ` Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric` to simplify the emitting process
* Changed `Shopwell\Core\Framework\Telemetry\Metrics\Metric\MetricInterface` with `Shopwell\Core\Framework\Telemetry\Metrics\Metric`
* Added the ability to configure labels on metrics
* Added the ability to disable and enable metrics individually
