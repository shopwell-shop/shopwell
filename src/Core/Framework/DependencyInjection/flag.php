<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Shopwell\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopwell\Core\Framework\Feature\FeatureFlagRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FeatureFlagRegistry::class)
        ->public()
        ->args([
            service(AbstractKeyValueStorage::class),
            service('event_dispatcher'),
            param('shopware.feature.flags'),
            param('shopware.feature_toggle.enable'),
        ]);
};
