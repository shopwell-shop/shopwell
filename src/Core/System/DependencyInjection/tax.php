<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;
use Shopwell\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Shopwell\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;
use Shopwell\Core\System\Tax\Api\TaxRateFkResolver;
use Shopwell\Core\System\Tax\TaxDefinition;
use Shopwell\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopwell\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Shopwell\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Shopwell\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(TaxDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(TaxRuleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(TaxRuleTypeDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(TaxRuleTypeTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(EntireCountryRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(IndividualStatesRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(ZipCodeRangeRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(ZipCodeRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(TaxRateFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.sync.fk_resolver');
};
