<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Shopwell\Core\Content\Media\File\FileUrlValidatorInterface;
use Shopwell\Core\Content\Test\Media\File\FileUrlValidatorStub;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FileUrlValidatorInterface::class, FileUrlValidatorStub::class);
};
