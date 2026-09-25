<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
class MeterProvider
{
    private static ?\Closure $meterProviderClosure = null;

    public static function bindMeter(ContainerInterface $container): void
    {
        self::$meterProviderClosure = static fn (): ?Meter => $container->has(Meter::class)
            ? $container->get(Meter::class)
            : null;
    }

    public static function meter(): ?Meter
    {
        return self::$meterProviderClosure ? \call_user_func(self::$meterProviderClosure) : null;
    }
}
