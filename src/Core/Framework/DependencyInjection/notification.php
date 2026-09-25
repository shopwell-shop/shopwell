<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Shopwell\Core\Framework\Notification\Api\NotificationController;
use Shopwell\Core\Framework\Notification\NotificationBulkEntityExtension;
use Shopwell\Core\Framework\Notification\NotificationDefinition;
use Shopwell\Core\Framework\Notification\NotificationService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(NotificationBulkEntityExtension::class)
        ->tag('shopwell.bulk.entity.extension');

    $services->set(NotificationService::class)
        ->public()
        ->args([
            service('notification.repository'),
        ]);

    $services->set(NotificationController::class)
        ->public()
        ->args([
            service('shopwell.rate_limiter'),
            service(NotificationService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NotificationDefinition::class)
        ->tag('shopwell.entity.definition');
};
