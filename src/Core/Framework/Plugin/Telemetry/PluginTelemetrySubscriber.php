<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Telemetry;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class PluginTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'emitPluginInstallCountMetric',
        ];
    }

    public function emitPluginInstallCountMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric(name: 'plugin.install.count', value: 1));
    }
}
