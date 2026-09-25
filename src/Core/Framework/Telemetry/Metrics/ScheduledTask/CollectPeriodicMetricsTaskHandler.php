<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: CollectPeriodicMetricsTask::class)]
final class CollectPeriodicMetricsTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param iterable<PeriodicMetricCollectorInterface> $collectors
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly Meter $meter,
        private readonly iterable $collectors,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->collectors as $collector) {
            try {
                // iterator_to_array forces generator-based collectors to fully materialize here, so any
                // exception thrown during collection is caught by this try/catch rather and does not influence
                // emitting metrics from other collectors.
                $metrics = iterator_to_array($collector->collect(), preserve_keys: false);
            } catch (\Throwable $e) {
                $this->exceptionLogger->error(
                    \sprintf('Periodic metric collector %s failed: %s', $collector::class, $e->getMessage()),
                    ['exception' => $e]
                );

                continue;
            }

            foreach ($metrics as $metric) {
                $this->meter->emit($metric);
            }
        }
    }
}
