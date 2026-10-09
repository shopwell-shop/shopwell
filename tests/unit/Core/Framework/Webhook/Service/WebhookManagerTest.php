<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\App\AppLocaleProvider;
use Shopwell\Core\Framework\App\Event\AppFlowActionEvent;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\Hmac\RequestSigner;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\App\Payload\Source;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\AppEventPolicy;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\PrivilegePolicy;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopwell\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopwell\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopwell\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopwell\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopwell\Core\Framework\Webhook\Service\WebhookClient;
use Shopwell\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopwell\Core\Framework\Webhook\Service\WebhookLoader;
use Shopwell\Core\Framework\Webhook\Service\WebhookManager;
use Shopwell\Core\Framework\Webhook\Service\WebhookRequest;
use Shopwell\Core\Framework\Webhook\Webhook;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookManager::class)]
#[DisabledFeatures(['WEBHOOKS_REWORK'])]
class WebhookManagerTest extends TestCase
{
    private WebhookLoader&MockObject $webhookLoader;

    private MockHandler $clientMock;

    private WebhookClient $webhookClient;

    private HookableEventFactory&MockObject $eventFactory;

    private CollectingMessageBus $bus;

    private WebhookOutboxStore&MockObject $webhookOutboxStore;

    protected function setUp(): void
    {
        $this->webhookLoader = $this->createMock(WebhookLoader::class);
        $this->clientMock = new MockHandler([new Response(200, [], '{}')]);
        $stack = HandlerStack::create($this->clientMock);
        $stack->push(new AuthMiddleware('6.7.0', static::createStub(AppLocaleProvider::class)));
        $guzzle = new Client(['handler' => $stack]);
        $this->webhookClient = new WebhookClient($guzzle, new NativeClock());
        $this->eventFactory = $this->createMock(HookableEventFactory::class);
        $this->bus = new CollectingMessageBus();
        $this->webhookOutboxStore = $this->createMock(WebhookOutboxStore::class);
        $this->webhookOutboxStore->method('markRunning')->willReturn(new OutboxEntry(
            webhookEventId: 'stub',
            sequence: 1,
            executionCount: 1,
            deliveryStatus: 'running',
        ));
    }

    public function testDispatchesTwoConsecutiveEventsCorrectly(): void
    {
        $event1 = new AppFlowActionEvent('foobar', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']);
        $event2 = new class('foobar.event', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']) extends AppFlowActionEvent {};

        $this->eventFactory
            ->expects($this->exactly(2))
            ->method('createHookablesFor')
            ->willReturn([$event1], [$event2]);

        // only the second dispatch matches a webhook and is delivered synchronously
        $this->webhookOutboxStore->expects($this->once())->method('markSuccess');

        $webhookManager = $this->getWebhookManager(true);
        $webhookManager->dispatch($event1);
        $request = $this->clientMock->getLastRequest();
        static::assertNull($request);

        $webhook = $this->prepareWebhook($event2->getName());
        $this->assertSyncWebhookIsSent($webhook, $event2, $webhookManager);
    }

    public function testDispatchWithWebhooksSync(): void
    {
        $event = $this->prepareEvent();
        $webhook = $this->prepareWebhook($event->getName());

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::anything(),
            );

        $this->assertSyncWebhookIsSent($webhook, $event);
    }

    public function testSyncDispatchMarksFailedOnNonUtf8FailureBody(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName());

        $this->clientMock->reset();
        $this->clientMock->append(new Response(500, [], pack('C*', 0xB1)));

        $this->webhookOutboxStore->expects($this->once())->method('markFailed')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::callback(static fn (DeliveryResponse $r): bool => $r->responseContent === null && $r->responseStatusCode === 500),
            )
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markSuccess');

        $this->getWebhookManager(true)->dispatch($event);
    }

    public function testSyncDispatchMarksSuccessOnNonUtf8ResponseHeaders(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName());

        $this->clientMock->reset();
        $this->clientMock->append(new Response(200, ['x-bad' => pack('C*', 0xB1)], '{}'));

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::callback(static fn (DeliveryResponse $r): bool => $r->responseContent === null && $r->responseStatusCode === 200),
            )
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markFailed');

        $this->getWebhookManager(true)->dispatch($event);
    }

    public function testDispatchWithWebhooksAsync(): void
    {
        $event = $this->prepareEvent();
        $webhook = $this->prepareWebhook($event->getName());

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertArrayHasKey('source', $payload);
        static::assertArrayHasKey('eventId', $payload['source']);
        unset($payload['source']['eventId']);
        static::assertEquals([
            'foo' => 'bar',
            'source' => [
                'url' => 'https://example.com',
                'appVersion' => $webhook->appVersion,
                'shopId' => 'foobar',
                'action' => $event->getName(),
                'inAppPurchases' => null,
            ],
        ], $payload);

        static::assertSame($message->getLanguageId(), Defaults::LANGUAGE_SYSTEM);
        static::assertSame($message->getAppId(), $webhook->appId);
        static::assertSame($message->getSecret(), $webhook->appSecret);
        static::assertSame($message->getShopwellVersion(), '0.0.0');
        static::assertSame($message->getUrl(), 'https://foo.bar');
        static::assertSame($message->getWebhookId(), $webhook->id);
        static::assertSame(['x-test-header' => 'test-header-val'], $message->getWebhookHeaders());
    }

    public function testWebhookSettingForLiveVersionOnlyIsIgnoredIfEventTypeDoesNotMatch(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName(), true);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksForLiveVersionOnlyAreCalledIfPayloadHasLiveVersion(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('product.written', true);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();

        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksAreNotDispatchedIfPrivilegesAreMissing(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('product.written', true, []);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);
        $messages = $this->bus->getMessages();
        static::assertCount(0, $messages);
    }

    public function testWebhookCacheKeepsInactiveAppStateUntilCleared(): void
    {
        $event = new AppFlowActionEvent('commercial_license.provided', [], ['foo' => 'bar']);
        $inactiveWebhook = $this->getWebhook($event->getName(), appActive: false);
        $activeWebhook = $this->getWebhook($event->getName());

        $this->eventFactory
            ->expects($this->exactly(3))
            ->method('createHookablesFor')
            ->with($event)
            ->willReturn([$event]);

        $this->webhookLoader->expects($this->exactly(2))
            ->method('getWebhooks')
            ->willReturn([$inactiveWebhook], [$activeWebhook]);

        $this->webhookLoader
            ->method('getPrivilegesForRoles')
            ->willReturnCallback(static function (array $roleIds): array {
                $privileges = [];
                foreach ($roleIds as $roleId) {
                    $privileges[$roleId] = new AclPrivilegeCollection([]);
                }

                return $privileges;
            });

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $webhookManager = $this->getWebhookManager(false);

        $webhookManager->dispatch($event);
        static::assertCount(0, $this->bus->getMessages());

        $webhookManager->dispatch($event);
        static::assertCount(0, $this->bus->getMessages());

        $webhookManager->clearInternalWebhookCache();
        $webhookManager->dispatch($event);
        static::assertCount(1, $this->bus->getMessages());
    }

    public function testWebhooksForLiveVersionOnlyAreIgnoredIfPayloadHasDifferentVersion(): void
    {
        $event = $this->prepareHookableEvent([
            [
                'id' => Uuid::randomHex(),
                'versionId' => Uuid::randomHex(),
            ],
        ]);

        $this->prepareWebhook('product.written', true, resolvesPrivileges: false);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(0, $messages);
    }

    public function testWebhooksForLiveVersionOnlyAreSentIfPayloadDoesNotHaveAnyVersionId(): void
    {
        $entityRepository = new StaticEntityRepository([], new CustomerDefinition());

        $event = $entityRepository->create([
            [
                'id' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        $eventByEntityName = $event->getEventByEntityName('customer');
        static::assertInstanceOf(EntityWrittenEvent::class, $eventByEntityName);
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $this->eventFactory->expects($this->once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        $this->prepareWebhook('customer.written', true, ['customer:read']);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksAreCalledForNonLiveVersionConfig(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('product.written');

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testPayloadOfWebhookForLiveVersionOnlyIsFiltered(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $payloads = [
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ];

        $event = $this->prepareHookableEvent($payloads);
        $this->prepareWebhook('product.written', true);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertCount(1, $payload['data']['payload']);
        static::assertNotFalse(json_encode($payload));
        static::assertStringContainsString($firstId, json_encode($payload));
        static::assertStringNotContainsString($secondId, json_encode($payload));
    }

    public function testPayloadIsLeftUnchangedForNonLiveVersionConfig(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $payloads = [
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ];

        $event = $this->prepareHookableEvent($payloads);
        $this->prepareWebhook('product.written');

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertCount(2, $payload['data']['payload']);
        static::assertNotFalse(json_encode($payload));
        static::assertStringContainsString($firstId, json_encode($payload));
        static::assertStringContainsString($secondId, json_encode($payload));
    }

    private function assertSyncWebhookIsSent(Webhook $webhook, AppFlowActionEvent $event, ?WebhookManager $webhookManager = null): void
    {
        $expectedRequest = new Request(
            'POST',
            $webhook->url,
            [
                'x-test-header' => 'test-header-val',
                'Content-Type' => 'application/json',
                'sw-version' => '0.0.0',
                'sw-context-language' => [Defaults::LANGUAGE_SYSTEM],
                'sw-user-language' => [''],
            ],
            json_encode([
                'foo' => 'bar',
                'source' => [
                    'url' => 'https://example.com',
                    'appVersion' => $webhook->appVersion,
                    'shopId' => 'foobar',
                    'action' => $event->getName(),
                    'inAppPurchases' => null,
                    'sequence' => 1,
                ],
            ], \JSON_THROW_ON_ERROR)
        );

        $webhookManager = $webhookManager ?? $this->getWebhookManager(true);
        $webhookManager->dispatch($event);

        $request = $this->clientMock->getLastRequest();

        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('foo.bar', $request->getUri()->getHost());

        $headers = $request->getHeaders();
        static::assertArrayHasKey(RequestSigner::SHOPWELL_SHOP_SIGNATURE, $headers);
        static::assertArrayHasKey('X-Shopwell-Event-Id', $headers);
        static::assertArrayHasKey('X-Shopwell-Sequence', $headers);
        static::assertArrayHasKey('X-Shopwell-Attempt', $headers);
        static::assertSame(['1'], $headers['X-Shopwell-Sequence']);
        static::assertSame(['0'], $headers['X-Shopwell-Attempt']);
        unset(
            $headers[RequestSigner::SHOPWELL_SHOP_SIGNATURE],
            $headers['Content-Length'],
            $headers['User-Agent'],
            $headers['X-Shopwell-Event-Id'],
            $headers['X-Shopwell-Sequence'],
            $headers['X-Shopwell-Attempt'],
        );
        static::assertEquals($expectedRequest->getHeaders(), $headers);

        $expectedContents = json_decode($expectedRequest->getBody()->getContents(), true);
        $contents = json_decode($request->getBody()->getContents(), true);
        static::assertIsArray($contents);
        static::assertArrayHasKey('timestamp', $contents);
        static::assertArrayHasKey('source', $contents);
        static::assertArrayHasKey('eventId', $contents['source']);
        unset($contents['timestamp'], $contents['source']['eventId']);
        static::assertEquals($expectedContents, $contents);
    }

    private function prepareEvent(): AppFlowActionEvent
    {
        $event = new AppFlowActionEvent('foobar', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']);

        $this->eventFactory
            ->expects($this->once())
            ->method('createHookablesFor')
            ->with($event)
            ->willReturn([$event]);

        return $event;
    }

    /**
     * @param list<array{id: string, versionId: string}>|null $payloads
     */
    private function prepareHookableEvent(?array $payloads = null): Event
    {
        $entityRepository = new StaticEntityRepository([], new ProductDefinition());

        $event = $entityRepository->create($payloads ?? [
            [
                'id' => Uuid::randomHex(),
                'versionId' => Defaults::LIVE_VERSION,
            ],
        ], Context::createDefaultContext());

        $eventByEntityName = $event->getEventByEntityName('product');
        static::assertInstanceOf(EntityWrittenEvent::class, $eventByEntityName);
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $this->eventFactory->expects($this->once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        return $event;
    }

    /**
     * @param list<string> $acl
     */
    private function prepareWebhook(string $eventName, bool $onlyLiveVersion = false, array $acl = ['product:read'], bool $resolvesPrivileges = true): Webhook
    {
        $webhook = $this->getWebhook($eventName, $onlyLiveVersion);
        static::assertIsString($webhook->appAclRoleId);

        $this->webhookLoader->expects($this->once())
            ->method('getWebhooks')
            ->willReturn([$webhook]);

        $this->webhookLoader
            ->expects($resolvesPrivileges ? $this->once() : $this->never())
            ->method('getPrivilegesForRoles')
            ->with([$webhook->appAclRoleId])
            ->willReturn([$webhook->appAclRoleId => new AclPrivilegeCollection($acl)]);

        return $webhook;
    }

    private function getWebhookManager(bool $isAdminWorkerEnabled): WebhookManager
    {
        $appPayloadServiceHelper = static::createStub(AppPayloadServiceHelper::class);
        $appPayloadServiceHelper->method('buildSource')->willReturn(new Source('https://example.com', 'foobar', '0.0.0'));
        $appPayloadServiceHelper->method('createWebhookRequest')->willReturnCallback($this->buildWebhookRequest(...));

        $deliveryService = static::createStub(WebhookDeliveryService::class);
        $deliveryService->method('buildRequest')->willReturnCallback(
            fn (WebhookEventMessage $message, OutboxEntry $entry): WebhookRequest => $this->buildWebhookRequestFromMessage($message, $entry, $appPayloadServiceHelper),
        );

        return new WebhookManager(
            $this->webhookLoader,
            $this->eventFactory,
            static::createStub(AppLocaleProvider::class),
            $appPayloadServiceHelper,
            $this->webhookClient,
            $this->bus,
            'https://example.com',
            '0.0.0',
            $isAdminWorkerEnabled,
            $deliveryService,
            $this->webhookOutboxStore,
            new PolicyRegistry([new AppEventPolicy(), new PrivilegePolicy($this->webhookLoader)]),
        );
    }

    private function buildWebhookRequestFromMessage(
        WebhookEventMessage $message,
        OutboxEntry $entry,
        AppPayloadServiceHelper $helper,
    ): WebhookRequest {
        $payload = $message->getPayload();
        if (isset($payload['source']) && \is_array($payload['source'])) {
            $payload['source']['sequence'] = $entry->sequence;
        }

        $headers = $message->getWebhookHeaders();
        $headers[WebhookDeliveryService::HEADER_EVENT_ID] = $message->getWebhookEventId();
        $headers[WebhookDeliveryService::HEADER_SEQUENCE] = (string) $entry->sequence;
        $headers[WebhookDeliveryService::HEADER_ATTEMPT] = (string) max(0, $entry->executionCount - 1);

        return $helper->createWebhookRequest(
            $payload,
            $message->getUrl(),
            $message->getShopwellVersion(),
            WebhookClient::CONNECT_TIMEOUT,
            WebhookClient::REQUEST_TIMEOUT,
            $message->getSecret(),
            $message->getLanguageId(),
            $message->getUserLocale(),
            $headers,
        );
    }

    /**
     * Minimal stand-in for AppPayloadServiceHelper::createWebhookRequest.
     * The real method is unit-tested in AppPayloadServiceHelperTest; here we only
     * need a valid WebhookRequest so the Guzzle MockHandler receives a sendable request.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $webhookHeaders
     */
    private function buildWebhookRequest(
        array $payload,
        string $url,
        string $shopwellVersion,
        int $connectionTimeout,
        int $requestTimeout,
        ?string $secret = null,
        ?string $languageId = null,
        ?string $userLocale = null,
        array $webhookHeaders = [],
    ): WebhookRequest {
        $payload['timestamp'] = time();
        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = ['Content-Type' => 'application/json', 'sw-version' => $shopwellVersion, ...$webhookHeaders];
        if ($languageId !== null && $userLocale !== null) {
            $headers[AuthMiddleware::SHOPWELL_CONTEXT_LANGUAGE] = $languageId;
            $headers[AuthMiddleware::SHOPWELL_USER_LANGUAGE] = $userLocale;
        }

        $options = ['connect_timeout' => $connectionTimeout, 'timeout' => $requestTimeout];
        if ($secret !== null) {
            $options[AuthMiddleware::APP_REQUEST_TYPE] = [AuthMiddleware::APP_SECRET => $secret];
        }

        return new WebhookRequest(new Request('POST', $url, $headers, $jsonPayload), $headers, $jsonPayload, time(), $options);
    }

    private function getWebhook(string $eventName, bool $onlyLiveVersion = false, bool $appActive = true): Webhook
    {
        return new Webhook(
            Uuid::randomHex(),
            'Cool Webhook',
            $eventName,
            'https://foo.bar',
            $onlyLiveVersion,
            Uuid::randomHex(),
            'Cool App',
            'local',
            $appActive,
            '0.0.0',
            'verysecret',
            Uuid::randomHex()
        );
    }
}
