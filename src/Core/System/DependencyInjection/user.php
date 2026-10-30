<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitDefinition;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitPrimarySubscriber;
use Shopwell\Core\System\User\Aggregate\UserPosition\UserPositionDefinition;
use Shopwell\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopwell\Core\System\User\Api\UserRecoveryController;
use Shopwell\Core\System\User\Api\UserValidationController;
use Shopwell\Core\System\User\Recovery\UserRecoveryService;
use Shopwell\Core\System\User\Service\UserValidationService;
use Shopwell\Core\System\User\UserDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UserDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UserOrganizationUnitDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UserPositionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UserConfigDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UserAccessKeyDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UserRecoveryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UserRecoveryService::class)
        ->args([
            service('user_recovery.repository'),
            service('user.repository'),
            service('router'),
            service('event_dispatcher'),
            service(SalesChannelContextService::class),
            service('sales_channel.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(UserRecoveryController::class)
        ->public()
        ->args([
            service(UserRecoveryService::class),
            service('shopwell.rate_limiter'),
            param('shopwell.api.user_recovery.enabled'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(UserValidationService::class)
        ->args([
            service('user.repository'),
        ]);

    $services->set(UserValidationController::class)
        ->public()
        ->args([
            service(UserValidationService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(UserOrganizationUnitPrimarySubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');
};
