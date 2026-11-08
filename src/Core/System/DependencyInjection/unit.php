<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Shopwell\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopwell\Core\System\Unit\Api\UnitUneceCodeController;
use Shopwell\Core\System\Unit\UnitDefinition;
use Shopwell\Core\System\Unit\UnitUneceCodes;
use Shopwell\Core\System\Unit\UnitValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UnitDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UnitTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(UnitUneceCodes::class);

    $services->set(UnitValidator::class)
        ->args([service(UnitUneceCodes::class)])
        ->tag('kernel.event_subscriber');

    $services->set(UnitUneceCodeController::class)
        ->public()
        ->args([service(UnitUneceCodes::class)]);
};
