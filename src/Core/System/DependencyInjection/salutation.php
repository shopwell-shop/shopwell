<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\System\Salutation\AbstractSalutationsSorter;
use Shopwell\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition;
use Shopwell\Core\System\Salutation\Api\SalutationKeyFkResolver;
use Shopwell\Core\System\Salutation\SalesChannel\SalesChannelSalutationDefinition;
use Shopwell\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Shopwell\Core\System\Salutation\SalutationSorter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SalutationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelSalutationDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(SalutationTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalutationRoute::class)
        ->public()
        ->args([
            service('sales_channel.salutation.repository'),
            service(CacheTagCollector::class),
        ]);

    $services->set(AbstractSalutationsSorter::class, SalutationSorter::class);

    $services->set(SalutationKeyFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');
};
