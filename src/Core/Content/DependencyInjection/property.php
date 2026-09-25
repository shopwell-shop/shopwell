<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Shopwell\Core\Content\Property\PropertyGroupDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(PropertyGroupDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PropertyGroupOptionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PropertyGroupOptionTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PropertyGroupTranslationDefinition::class)
        ->tag('shopware.entity.definition');
};
