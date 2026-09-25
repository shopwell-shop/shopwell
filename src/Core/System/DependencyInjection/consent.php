<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\System\Consent\Api\ConsentController;
use Shopwell\Core\System\Consent\ConsentDefinitionRegistry;
use Shopwell\Core\System\Consent\ConsentRepository;
use Shopwell\Core\System\Consent\ConsentScope;
use Shopwell\Core\System\Consent\Definition;
use Shopwell\Core\System\Consent\Event\ConsentHookableEventDescriber;
use Shopwell\Core\System\Consent\Log\ConsentChangedSubscriber;
use Shopwell\Core\System\Consent\Log\ConsentLogInterface;
use Shopwell\Core\System\Consent\Log\DatabaseLog;
use Shopwell\Core\System\Consent\Service\ConsentService;
use Shopwell\Core\System\Consent\Subscriber\SetupStagingEventSubscriber;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConsentController::class)
        ->public()
        ->args([
            new Reference(ConsentService::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(ConsentRepository::class)
        ->args([
            new Reference(Connection::class),
            new Reference(ClockInterface::class),
        ]);

    $services->set(ConsentDefinitionRegistry::class)
        ->args([
            new TaggedIteratorArgument('shopwell.consent.definition'),
        ]);

    $services->set(ConsentService::class)
        ->args([
            new TaggedIteratorArgument('shopwell.consent.scope'),
            new Reference(ConsentDefinitionRegistry::class),
            new Reference(ConsentRepository::class),
            new Reference('event_dispatcher'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ConsentScope\System::class)
        ->tag('shopwell.consent.scope');

    $services->set(ConsentScope\AdminUser::class)
        ->tag('shopwell.consent.scope');

    $services->set(Definition\BackendData::class)
        ->tag('shopwell.consent.definition');

    $services->set(Definition\ProductAnalytics::class)
        ->tag('shopwell.consent.definition');

    $services->set(ConsentLogInterface::class)
        ->class(DatabaseLog::class)
        ->args([
            new Reference(Connection::class),
            new Reference(ClockInterface::class),
        ]);

    $services->set(ConsentChangedSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference(ConsentLogInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $services->set(SetupStagingEventSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference(Connection::class),
        ]);

    $services->set(ConsentHookableEventDescriber::class)
        ->args([
            new Reference(ConsentDefinitionRegistry::class),
        ])
        ->tag('shopwell.hookable_event.describer');
};
