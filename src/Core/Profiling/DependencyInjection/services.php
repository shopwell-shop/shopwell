<?php declare(strict_types=1);

namespace Shopwell\Core\Profiling\DependencyInjection;

use Shopwell\Core\Framework\Adapter\Command\CacheWatchDelayedCommand;
use Shopwell\Core\Profiling\Integration\Datadog;
use Shopwell\Core\Profiling\Integration\ServerTiming;
use Shopwell\Core\Profiling\Integration\Stopwatch;
use Shopwell\Core\Profiling\Integration\Tideways;
use Shopwell\Core\Profiling\Profiler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(Stopwatch::class)
        ->args([
            service('debug.stopwatch')->nullOnInvalid(),
        ])
        ->tag('shopwell.profiler', ['integration' => 'Symfony']);

    $services->set(Tideways::class)
        ->tag('shopwell.profiler', ['integration' => 'Tideways']);

    $services->set(CacheWatchDelayedCommand::class)
        ->tag('console.command')
        ->args([
            service('service_container'),
        ]);

    $services->set(Datadog::class)
        ->tag('shopwell.profiler', ['integration' => 'Datadog']);

    $services->set(ServerTiming::class)
        ->tag('shopwell.profiler', ['integration' => 'ServerTiming'])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponseEvent']);

    $services->set(Profiler::class)
        ->public()
        ->args([
            tagged_iterator('shopwell.profiler', 'integration'),
            param('shopwell.profiler.integrations'),
        ]);
};
