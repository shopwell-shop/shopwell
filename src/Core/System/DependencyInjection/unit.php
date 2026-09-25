<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Shopwell\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopwell\Core\System\Unit\UnitDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UnitDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UnitTranslationDefinition::class)
        ->tag('shopwell.entity.definition');
};
