<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Monolog\Handler\NullHandler;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopwell\Core\Content\Media\File\TrustedUrlResolver;
use Shopwell\Core\Content\Test\Category\Service\CountingEntityReader;
use Shopwell\Core\Content\Test\Category\Service\CountingEntitySearcher;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopwell\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Shopwell\Core\Framework\Test\App\StaticTrustedUrlResolverFactory;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateTimeDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NamedDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NamedOptionalGroupDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\RootDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubCascadeDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubManyDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedReferenceDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedRelationDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition\TestDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition\TestTranslationDefinition;
use Shopwell\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;
use Shopwell\Core\Framework\Test\MessageQueue\fixtures\TestMessageHandler;
use Shopwell\Core\Framework\Test\TestCacheClearer;
use Shopwell\Core\Framework\Test\TestCaseHelper\StoreApiSessionListener;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Test\TestSessionStorageFactory;
use Shopwell\Core\Framework\Test\Webhook\StaticWebhookTargetValidatorFactory;
use Shopwell\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\Test\Integration\App\GuzzleHistoryCollector;
use Shopwell\Core\Test\Integration\App\TestAppServer;
use Shopwell\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\TraceableMessageBus;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('monolog', [
        'handlers' => [
            'business_event_handler_discard' => [
                'id' => NullHandler::class,
                'type' => 'service',
                'priority' => 1,
                'channels' => ['business_events'],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->public();

    $services->set(TestPaymentHandler::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopware.payment.method');

    $services->set(MemoryAdapterFactory::class)
        ->tag('shopware.filesystem.factory');

    $services->set(NamedDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(NamedOptionalGroupDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RootDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'root']);

    $services->set(SubDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'root_sub']);

    $services->set(SubCascadeDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'root_sub_cascade']);

    $services->set(SubManyDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'root_sub_many']);

    $services->set(TestDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => '_test_lock']);

    $services->set(TestTranslationDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => '_test_lock_translation']);

    $services->set(CustomFieldTestDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'attribute_test']);

    $services->set(CustomFieldTestTranslationDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'attribute_test_translation']);

    $services->set(WriteProtectedDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => '_test_nullable']);

    $services->set(WriteProtectedRelationDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => '_test_relation']);

    $services->set(WriteProtectedReferenceDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => '_test_nullable_reference']);

    $services->set(ExtendedProductDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'extended_product']);

    $services->set(DateTimeDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'date_time_test']);

    $services->alias('messenger.test_receiver_locator', 'messenger.receiver_locator')
        ->public();

    $services->set('messenger.bus.test_shopware', TraceableMessageBus::class)
        ->decorate('messenger.default_bus')
        ->args([
            service('messenger.bus.test_shopware.inner'),
        ]);

    $services->set('mailer.mailer', Mailer::class)
        ->args([
            service('mailer.transports'),
            service('messenger.default_bus'),
            service('debug.event_dispatcher')->ignoreOnInvalid(),
        ]);

    $services->alias('test.browser', 'test.client');

    $services->set(StoreApiSessionListener::class)
        ->tag('kernel.event_subscriber');

    $services->set('test.client', TestBrowser::class)
        ->share(false)
        ->public()
        ->args([
            service('kernel'),
            param('test.client.parameters'),
            service('test.client.history'),
            service('test.client.cookiejar'),
        ]);

    $services->set(NullHandler::class);

    $services->set(TestMessageHandler::class)
        ->tag('messenger.message_handler');

    $services->set(CountingEntityReader::class)
        ->decorate(EntityReaderInterface::class)
        ->args([
            service(CountingEntityReader::class . '.inner'),
        ]);

    $services->set(CountingEntitySearcher::class)
        ->decorate(EntitySearcherInterface::class)
        ->args([
            service(CountingEntitySearcher::class . '.inner'),
        ]);

    $services->set(TestCacheClearer::class)
        ->args([
            [
                service('cache.object'),
                service('cache.http'),
                service('cache.rate_limiter'),
            ],
            service('cache_clearer'),
            param('kernel.cache_dir'),
        ]);

    $services->set('shopware.app_system.guzzle', Client::class)
        ->args([
            [
                'allow_redirects' => AuthMiddleware::ALLOW_REDIRECTS,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service(TestAppServer::class),
                    ])
                    ->call('after', [
                        'allow_redirects',
                        service('shopware.app_system.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('shopware.app_system.guzzle.middleware'),
                    ])
                    ->call('push', [
                        service('test.guzzle.history.middleware'),
                    ]),
            ],
        ]);

    $services->set('shopware.app_system.trusted_url_resolver', TrustedUrlResolver::class)
        ->factory([StaticTrustedUrlResolverFactory::class, 'create'])
        ->args([
            param('shopware.app_system.allowed_private_ip_addresses'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('shopware.webhook.trusted_url_resolver', TrustedUrlResolver::class)
        ->factory([StaticTrustedUrlResolverFactory::class, 'create'])
        ->args([
            param('shopware.app_system.allowed_private_ip_addresses'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('shopware.webhook.guzzle', Client::class)
        ->args([
            [
                'allow_redirects' => AuthMiddleware::ALLOW_REDIRECTS,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service(TestAppServer::class),
                    ])
                    ->call('after', [
                        'allow_redirects',
                        service('shopware.webhook.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('shopware.app_system.guzzle.middleware'),
                    ])
                    ->call('push', [
                        service('test.guzzle.history.middleware'),
                    ]),
            ],
        ]);

    $services->set('Shopwell\Core\Framework\Webhook\Validation\WebhookTargetValidator', WebhookTargetValidator::class)
        ->factory([StaticWebhookTargetValidatorFactory::class, 'create'])
        ->args([
            param('shopware.app_system.allow_unencrypted_traffic'),
            param('shopware.app_system.allowed_private_ip_addresses'),
            param('shopware.app_system.enable_url_validation'),
        ]);

    $services->set(TestAppServer::class)
        ->args([
            service(MockHandler::class),
        ]);

    $services->set(MockHandler::class)
        ->public()
        ->args([
            [],
        ]);

    $services->set('test.guzzle.history.middleware', 'callable')
        ->factory([service(GuzzleHistoryCollector::class), 'getHistoryMiddleWare']);

    $services->set(GuzzleHistoryCollector::class)
        ->public();

    $services->set(AclTestController::class)
        ->public();

    $services->set('test_payment_decoration', AppPaymentHandler::class)
        ->decorate(AppPaymentHandler::class)
        ->args([
            service(StateMachineRegistry::class),
            service(PaymentPayloadService::class),
            service('order_transaction_capture_refund.repository'),
            service('order_transaction.repository'),
            service('app.repository'),
            service(Connection::class),
        ]);

    $services->set(TestSessionStorageFactory::class)
        ->decorate('session.storage.factory.mock_file');
};
