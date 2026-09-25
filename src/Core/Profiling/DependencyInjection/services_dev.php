<?php declare(strict_types=1);

namespace Shopwell\Core\Profiling\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Cart\CartPersister;
use Shopwell\Core\Framework\Feature\FeatureFlagRegistry;
use Shopwell\Core\Profiling\Controller\ProfilerController;
use Shopwell\Core\Profiling\Doctrine\ConnectionProfiler;
use Shopwell\Core\Profiling\FeatureFlag\FeatureFlagProfiler;
use Shopwell\Core\Profiling\Routing\ProfilerWhitelist;
use Shopwell\Core\Profiling\Subscriber\ActiveRulesDataCollectorSubscriber;
use Shopwell\Core\Profiling\Subscriber\CacheTagCollectorSubscriber;
use Shopwell\Core\Profiling\Subscriber\CartDataCollectorSubscriber;
use Shopwell\Core\Profiling\Twig\DoctrineExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ProfilerController::class)
        ->args([
            service('twig'),
            service('profiler'),
            service(Connection::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(ProfilerWhitelist::class)
        ->tag('shopwell.route_scope_whitelist');

    $services->set(ConnectionProfiler::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('data_collector', [
            'template' => '@Profiling/Collector/db.html.twig',
            'id' => 'app.connection_collector',
            'priority' => 200,
        ]);

    $services->set(DoctrineExtension::class)
        ->tag('twig.extension');

    $services->set(ActiveRulesDataCollectorSubscriber::class)
        ->args([
            service('rule.repository'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('data_collector');

    $services->set(FeatureFlagProfiler::class)
        ->args([
            service(FeatureFlagRegistry::class),
        ])
        ->tag('data_collector', [
            'template' => '@Profiling/Collector/flags.html.twig',
            'id' => 'feature_flag',
            'priority' => -5,
        ]);

    $services->set(CacheTagCollectorSubscriber::class)
        ->args([
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('data_collector');

    $services->set(CartDataCollectorSubscriber::class)
        ->args([
            service(CartPersister::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('data_collector');
};
