<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopwell\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Shopwell\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopwell\Core\Content\Rule\DataAbstractionLayer\RuleIndexer;
use Shopwell\Core\Content\Rule\DataAbstractionLayer\RuleIndexerSubscriber;
use Shopwell\Core\Content\Rule\DataAbstractionLayer\RulePayloadSubscriber;
use Shopwell\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Shopwell\Core\Content\Rule\RuleDefinition;
use Shopwell\Core\Content\Rule\RuleValidator;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RuleConditionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RuleTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RuleValidator::class)
        ->args([
            service('validator'),
            service(RuleConditionRegistry::class),
            service('rule_condition.repository'),
            service('app_script_condition.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RulePayloadSubscriber::class)
        ->args([
            service(RulePayloadUpdater::class),
            service('service_container'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RuleIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('rule.repository'),
            service(RulePayloadUpdater::class),
            service(RuleAreaUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(RuleIndexerSubscriber::class)
        ->args([
            service(Connection::class),
            service(CartRuleLoader::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RulePayloadUpdater::class)
        ->args([
            service(Connection::class),
            service(RuleConditionRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RuleAreaUpdater::class)
        ->args([
            service(Connection::class),
            service(RuleDefinition::class),
            service(RuleConditionRegistry::class),
            service(CacheInvalidator::class),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');
};
