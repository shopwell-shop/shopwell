<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Acl\AclAnnotationValidator;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\Api\Acl\AclWriteValidator;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopwell\Core\Framework\Api\Controller\AclController;
use Shopwell\Core\Framework\Api\EventListener\Acl\CreditOrderLineItemListener;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AclRoleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AclUserRoleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AclWriteValidator::class)
        ->args([
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AclAnnotationValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AclCriteriaValidator::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(CreditOrderLineItemListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(AclController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('event_dispatcher'),
            service('router'),
        ])
        ->call('setContainer', [service('service_container')]);
};
