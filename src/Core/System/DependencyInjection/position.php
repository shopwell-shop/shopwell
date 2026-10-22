<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Shopwell\Core\System\Position\Aggregate\PositionTranslation\PositionTranslationDefinition;
use Shopwell\Core\System\Position\PositionDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(PositionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PositionTranslationDefinition::class)
        ->tag('shopwell.entity.definition');
};
