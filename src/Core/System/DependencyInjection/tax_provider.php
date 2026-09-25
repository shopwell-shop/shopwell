<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Shopwell\Core\System\TaxProvider\Aggregate\TaxProviderTranslation\TaxProviderTranslationDefinition;
use Shopwell\Core\System\TaxProvider\TaxProviderDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(TaxProviderDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(TaxProviderTranslationDefinition::class)
        ->tag('shopware.entity.definition');
};
