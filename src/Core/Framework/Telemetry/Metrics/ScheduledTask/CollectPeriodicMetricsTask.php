<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal
 */
#[Package('framework')]
class CollectPeriodicMetricsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'telemetry.collect_periodic_metrics';
    }

    public static function getDefaultInterval(): int
    {
        return 5 * self::MINUTELY;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('shopwell.telemetry.metrics.enabled');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
