<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Telemetry;

use Shopwell\Core\Framework\App\Event\AppInstalledEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class AppTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'emitAppInstalledMetric',
        ];
    }

    public function emitAppInstalledMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric(name: 'app.install.count', value: 1));
    }
}
