<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopwell\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopwell\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopwell\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class Meter
{
    /**
     * @internal
     *
     * @param TransportCollection<MetricTransportInterface> $transports
     */
    public function __construct(
        private readonly TransportCollection $transports,
        private readonly MetricConfigProvider $metricConfigProvider,
        private readonly MetricLabelProcessor $labelProcessor,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
        private readonly bool $enabled,
    ) {
    }

    public function emit(ConfiguredMetric $metric): void
    {
        if (!$this->enabled || !Feature::isActive('TELEMETRY_METRICS')) {
            return;
        }

        $metric = $this->process($metric);
        if ($metric === null) {
            return;
        }

        foreach ($this->transports as $transport) {
            $this->doEmitVia($metric, $transport);
        }
    }

    private function process(ConfiguredMetric $metric): ?Metric
    {
        try {
            $metricConfig = $this->metricConfigProvider->get($metric->name);
            if (!$metricConfig->enabled) {
                return null;
            }

            $processedLabels = $this->labelProcessor->process($metricConfig, $metric->labels);
            if ($processedLabels === null) {
                return null;
            }

            return Metric::fromConfigured(configuredMetric: $metric, metricConfig: $metricConfig, processedLabels: $processedLabels);
        } catch (MissingMetricConfigurationException $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            if ($this->environment === 'dev' || $this->environment === 'test') {
                throw $exception;
            }

            return null;
        }
    }

    private function doEmitVia(Metric $metric, MetricTransportInterface $transport): void
    {
        try {
            $transport->emit($metric);
        } catch (\Throwable $e) {
            $this->logger->warning(
                $e instanceof MetricNotSupportedException ? $e->getMessage() : \sprintf('Failed to emit metric via transport %s', $transport::class),
                ['exception' => $e]
            );

            if ($this->environment === 'dev' || $this->environment === 'test') {
                throw $e;
            }
        }
    }
}
