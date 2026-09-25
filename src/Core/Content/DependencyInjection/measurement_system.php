<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Shopwell\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopwell\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementSystemEntity;
use Shopwell\Core\Content\MeasurementSystem\Field\MeasurementUnitsFieldSerializer;
use Shopwell\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopwell\Core\Content\MeasurementSystem\TwigExtension\MeasurementConvertUnitTwigFilter;
use Shopwell\Core\Content\MeasurementSystem\Unit\MeasurementUnitConverter;
use Shopwell\Core\Content\MeasurementSystem\Unit\MeasurementUnitProvider;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MeasurementSystemEntity::class)
        ->tag('shopware.entity');

    $services->set(MeasurementDisplayUnitEntity::class)
        ->tag('shopware.entity');

    $services->set(MeasurementUnitsFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(MeasurementUnitProvider::class)
        ->args([
            service('measurement_display_unit.repository'),
        ]);

    $services->set(MeasurementUnitConverter::class)
        ->args([
            service(MeasurementUnitProvider::class),
        ]);

    $services->set(ProductMeasurementUnitBuilder::class)
        ->args([
            service(MeasurementUnitConverter::class),
        ]);

    $services->set(MeasurementConvertUnitTwigFilter::class)
        ->args([
            service(MeasurementUnitProvider::class),
            service(MeasurementUnitConverter::class),
        ])
        ->tag('twig.extension');
};
