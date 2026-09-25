<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Shopwell\Core\Framework\Validation\Api\EmailValidationController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(EmailValidationController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);
};
