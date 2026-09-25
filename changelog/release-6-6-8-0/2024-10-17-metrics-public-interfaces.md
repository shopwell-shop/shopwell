---
title: Metrics public interfaces
issue: NEXT-37689
---
# Core
* Changed parts of the future public interface (metrics emitting view) from @internal to @experimental:
  * `Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric`
  * `Shopwell\Core\Framework\Telemetry\Metrics\Meter`
* Changed parts of the future public interface (metrics transport view) from @internal to @experimental (`Metric` and `Type` classes are public already):
  * `Shopwell\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface`
  * `Shopwell\Core\Framework\Telemetry\Metrics\MetricTransportInterface`
  * `Shopwell\Core\Framework\Telemetry\TelemetryException` 
* Added factory method `fromArray` to `Shopwell\Core\Framework\Telemetry\Metrics\Metric\Metric` (to simplify testing of transports and keep `MetricConfig` internal)
