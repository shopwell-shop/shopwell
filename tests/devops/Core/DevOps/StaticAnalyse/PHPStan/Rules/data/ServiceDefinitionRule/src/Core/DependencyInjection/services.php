<?php declare(strict_types=1);

use Shopwell\Core\Framework\Example\CoreServiceInCore;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(CoreServiceInCore::class);
};
