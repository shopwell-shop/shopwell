<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopwell\Core\System\Organization\Aggregate\OrganizationTranslation\OrganizationTranslationDefinition;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationDefinition;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\DataAbstractionLayer\OrganizationUnitIndexer;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;
use Shopwell\Core\System\Organization\OrganizationDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(OrganizationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(OrganizationTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(OrganizationUnitDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(OrganizationUnitTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(OrganizationUnitIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('organization_unit.repository'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
        ])
        ->tag('shopwell.entity_indexer');
};
