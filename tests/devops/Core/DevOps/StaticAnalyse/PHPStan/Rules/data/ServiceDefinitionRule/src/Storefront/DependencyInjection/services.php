<?php declare(strict_types=1);

use Shopwell\Core\Framework\Example\CoreContract;
use Shopwell\Core\Framework\Example\PhpCoreService;
use Shopwell\Storefront\Framework\Example\StorefrontImplementation;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(CoreContract::class, StorefrontImplementation::class);

    $services->set(PhpCoreService::class);
};
