<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Shopwell\Core\Framework\Rule\Api\RuleConfigController;
use Shopwell\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopwell\Core\Framework\Rule\Container\NotRule;
use Shopwell\Core\Framework\Rule\Container\OrRule;
use Shopwell\Core\Framework\Rule\Container\XorRule;
use Shopwell\Core\Framework\Rule\DateRangeRule;
use Shopwell\Core\Framework\Rule\RuleIdMatcher;
use Shopwell\Core\Framework\Rule\SalesChannelRule;
use Shopwell\Core\Framework\Rule\ScriptRule;
use Shopwell\Core\Framework\Rule\SimpleRule;
use Shopwell\Core\Framework\Rule\TimeRangeRule;
use Shopwell\Core\Framework\Rule\WeekdayRule;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RuleConditionRegistry::class)
        ->args([
            tagged_iterator('shopware.rule.definition'),
        ]);

    $services->set(RuleIdMatcher::class);

    $services->set(AndRule::class)
        ->tag('shopware.rule.definition');

    $services->set(NotRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrRule::class)
        ->tag('shopware.rule.definition');

    $services->set(XorRule::class)
        ->tag('shopware.rule.definition');

    $services->set(MatchAllLineItemsRule::class)
        ->tag('shopware.rule.definition');

    $services->set(ScriptRule::class)
        ->tag('shopware.rule.definition');

    $services->set(DateRangeRule::class)
        ->tag('shopware.rule.definition');

    $services->set(SimpleRule::class)
        ->tag('shopware.rule.definition');

    $services->set(SalesChannelRule::class)
        ->tag('shopware.rule.definition');

    $services->set(TimeRangeRule::class)
        ->tag('shopware.rule.definition');

    $services->set(WeekdayRule::class)
        ->tag('shopware.rule.definition');

    $services->set(RuleConfigController::class)
        ->public()
        ->args([
            tagged_iterator('shopware.rule.definition'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
