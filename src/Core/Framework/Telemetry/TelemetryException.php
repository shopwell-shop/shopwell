<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopwell\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopwell\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class TelemetryException extends HttpException
{
    public const UNKNOWN_LABEL_NAME = 'TELEMETRY__UNKNOWN_METRIC_LABEL';

    public const INSTRUMENT_WITHOUT_METRIC_OR_SPAN = 'TELEMETRY__INSTRUMENT_WITHOUT_METRIC_OR_SPAN';

    public static function metricNotSupported(
        Metric $metric,
        MetricTransportInterface $transport
    ): MetricNotSupportedException {
        return new MetricNotSupportedException(
            metric: $metric,
            transport: $transport,
            message: \sprintf('Metric %s, not supported by transport %s', $metric::class, $transport::class),
        );
    }

    /**
     * @internal
     */
    public static function metricMissingConfiguration(string $metric): MissingMetricConfigurationException
    {
        return new MissingMetricConfigurationException(
            metric: $metric,
            message: \sprintf('Missing configuration for metric %s', $metric),
        );
    }

    /**
     * @internal
     */
    public static function unknownMetricLabel(string $metricName, string $labelName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_LABEL_NAME,
            \sprintf('Unknown label "%s" for metric "%s". The label must be declared in the metric definition.', $labelName, $metricName),
        );
    }

    /**
     * @internal
     */
    public static function instrumentWithoutMetricOrSpan(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INSTRUMENT_WITHOUT_METRIC_OR_SPAN,
            'Telemetry::instrument() was called without a DurationMetric or a Span. Provide at least one — otherwise call the callback directly.',
        );
    }
}
