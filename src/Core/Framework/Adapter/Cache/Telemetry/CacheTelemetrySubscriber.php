<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache\Telemetry;

use Shopwell\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class CacheTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvalidateCacheEvent::class => 'emitInvalidateCacheCountMetric',
        ];
    }

    public function emitInvalidateCacheCountMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric('cache.invalidate.count', 1));
    }
}
