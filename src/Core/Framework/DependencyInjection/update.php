<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Notification\NotificationService;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Update\Api\UpdateController;
use Shopwell\Core\Framework\Update\Services\ApiClient;
use Shopwell\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopwell\Core\Framework\Update\Services\UpdateHtaccess;
use Shopwell\Core\Framework\Update\Subscriber\UpdateSubscriber;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UpdateController::class)
        ->public()
        ->args([
            service(ApiClient::class),
            service(StoreClient::class),
            service(ExtensionCompatibility::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(ExtensionLifecycleService::class),
            param('kernel.shopwell_version'),
            param('shopwell.auto_update.enabled'),
            param('shopwell.auto_update.hide_module'),
            param('shopwell.deployment.cluster_setup'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ApiClient::class)
        ->args([
            service('http_client'),
            param('kernel.shopwell_version'),
            param('kernel.project_dir'),
            service(ClockInterface::class),
        ]);

    $services->set(ExtensionCompatibility::class)
        ->args([
            service(StoreClient::class),
            service(AbstractExtensionDataProvider::class),
            service('event_dispatcher'),
        ]);

    $services->set(UpdateHtaccess::class)
        ->args([
            '%kernel.project_dir%/public/.htaccess',
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UpdateSubscriber::class)
        ->args([
            service(NotificationService::class),
        ])
        ->tag('kernel.event_subscriber');
};
