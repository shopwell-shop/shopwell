<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopwell\Core\System\Tag\Service\FilterTagIdsService;
use Shopwell\Core\System\Tag\TagDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(TagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(FilterTagIdsService::class)
        ->public()
        ->args([
            service(TagDefinition::class),
            service(Connection::class),
            service(CriteriaQueryBuilder::class),
        ]);
};
