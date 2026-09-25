<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\System\CustomEntity\Api\CustomEntityApiController;
use Shopwell\Core\System\CustomEntity\CustomEntityDefinition;
use Shopwell\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopwell\Core\System\CustomEntity\Schema\CustomEntityNameValidator;
use Shopwell\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopwell\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopwell\Core\System\CustomEntity\Schema\SchemaUpdater;
use Shopwell\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopwell\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Lock\LockFactory;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomEntityRegistrar::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(CustomEntityPersister::class)
        ->args([
            service(Connection::class),
            service('cache.object'),
            service(ClockInterface::class),
        ]);

    $services->set(CustomEntityDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomEntityNameValidator::class);

    $services->set(SchemaUpdater::class)
        ->args([
            service(CustomEntityNameValidator::class),
        ]);

    $services->set(CustomEntitySchemaUpdater::class)
        ->public()
        ->args([
            service(Connection::class),
            service(LockFactory::class),
            service(SchemaUpdater::class),
        ]);

    $services->set(CustomEntityApiController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('serializer'),
            service(RequestCriteriaBuilder::class),
            service(EntityProtectionValidator::class),
            service(AclCriteriaValidator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(CustomEntityXmlSchemaValidator::class)
        ->args([
            service(CustomEntityNameValidator::class),
        ]);
    $services->set(AdminUiXmlSchemaValidator::class);
};
