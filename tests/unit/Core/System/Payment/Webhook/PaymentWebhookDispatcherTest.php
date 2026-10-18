<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Webhook;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentWebhookDispatcher::class)]
class PaymentWebhookDispatcherTest extends TestCase
{
    private const APP_ID = 'app-id';

    public function testEnqueueReturnsNullWhenTheOrderHasNoNotifyUrl(): void
    {
        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        $order = $this->order(null);

        static::assertNull($service->enqueue($order, PaymentWebhookDispatcher::EVENT_ORDER_PAID));
    }

    public function testEnqueueReturnsNullWhenTheAppDoesNotExistAnymore(): void
    {
        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $service = $this->service($deliveryRepository, $this->appRepository(null));

        $order = $this->order('https://app.example.test/webhook');

        static::assertNull($service->enqueue($order, PaymentWebhookDispatcher::EVENT_ORDER_PAID));
    }

    public function testEnqueuePersistsASignedPendingDelivery(): void
    {
        $created = $this->delivery('delivery-id', 0);

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                $row = $data[0];

                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertSame(self::APP_ID, $row['paymentAppId']);
                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_PAID, $row['event']);
                // an event that happens once per order names the order as its subject
                static::assertSame(PaymentWebhookDispatcher::SUBJECT_ORDER, $row['subject']);
                static::assertSame('https://app.example.test/webhook', $row['url']);
                static::assertSame(PaymentWebhookDeliveryEntity::STATUS_PENDING, $row['status']);
                static::assertSame(0, $row['attemptCount']);
                static::assertSame('PG0001', $row['payload']['payment_no']);
                static::assertSame('app-trade-1', $row['payload']['out_trade_no']);
                static::assertSame(['foo' => 'bar'], $row['payload']['data']);
                static::assertSame(1704067200, $row['payload']['timestamp']);

                $expected = (new PaymentSignatureService())->signPayload(
                    'app-secret',
                    (string) json_encode($row['payload'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
                );

                static::assertSame($expected, $row['signature']);
                static::assertNotSame('', $row['signature']);

                return true;
            }));

        // the lookup for an already queued delivery finds none, the re-read after the write finds it
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            $this->deliverySearchResult(),
            $this->deliverySearchResult($created)
        );

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        $result = $service->enqueue($this->order('https://app.example.test/webhook'), PaymentWebhookDispatcher::EVENT_ORDER_PAID, ['foo' => 'bar']);

        static::assertSame($created, $result);
    }

    public function testEnqueueReturnsTheQueuedDeliveryInsteadOfQueueingASecondOne(): void
    {
        $existing = $this->delivery('delivery-id', 0);

        // the queued row is the retry mechanism itself, queueing it twice would send the event twice
        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');
        $deliveryRepository->expects($this->never())->method('update');
        $deliveryRepository->method('search')->willReturn($this->deliverySearchResult($existing));

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        static::assertSame($existing, $service->enqueue($this->order('https://app.example.test/webhook'), PaymentWebhookDispatcher::EVENT_ORDER_PAID));
    }

    public function testEnqueueReturnsTheDeliveredDeliveryWhenTheAppAlreadyKnows(): void
    {
        $existing = $this->delivery('delivery-id', 1);
        $existing->status = PaymentWebhookDeliveryEntity::STATUS_SUCCESS;

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $deliveryRepository->method('search')->willReturn($this->deliverySearchResult($existing));

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        static::assertSame($existing, $service->enqueue($this->order('https://app.example.test/webhook'), PaymentWebhookDispatcher::EVENT_ORDER_PAID));
    }

    public function testEnqueueDoesNotReArmADeliveryThatWasGivenUp(): void
    {
        $existing = $this->delivery('delivery-id', PaymentWebhookDispatcher::MAX_ATTEMPTS);
        $existing->status = PaymentWebhookDeliveryEntity::STATUS_GIVING_UP;

        // giving up is a decision that was made on purpose and logged as such, a duplicate callback
        // must not reset the attempts behind that decision
        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');
        $deliveryRepository->expects($this->never())->method('update');
        $deliveryRepository->method('search')->willReturn($this->deliverySearchResult($existing));

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        static::assertSame($existing, $service->enqueue($this->order('https://app.example.test/webhook'), PaymentWebhookDispatcher::EVENT_ORDER_PAID));
    }

    public function testEnqueueQueuesOneDeliveryPerSubjectOfTheSameOrder(): void
    {
        $first = $this->delivery('delivery-1', 0);
        $first->event = PaymentWebhookDispatcher::EVENT_REFUND_FINISHED;
        $first->subject = 'refund-1';

        $second = $this->delivery('delivery-2', 0);
        $second->event = PaymentWebhookDispatcher::EVENT_REFUND_FINISHED;
        $second->subject = 'refund-2';

        $subjects = [];
        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->exactly(2))
            ->method('create')
            ->with(static::callback(static function (array $data) use (&$subjects): bool {
                $subjects[] = $data[0]['subject'];

                return true;
            }));
        // find, reload, find, reload
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            $this->deliverySearchResult(),
            $this->deliverySearchResult($first),
            $this->deliverySearchResult(),
            $this->deliverySearchResult($second)
        );

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));
        $order = $this->order('https://app.example.test/webhook');

        static::assertSame($first, $service->enqueue($order, PaymentWebhookDispatcher::EVENT_REFUND_FINISHED, [], 'refund-1'));
        static::assertSame($second, $service->enqueue($order, PaymentWebhookDispatcher::EVENT_REFUND_FINISHED, [], 'refund-2'));
        static::assertSame(['refund-1', 'refund-2'], $subjects);
    }

    public function testEnqueueStoresTheSubjectOfTheEvent(): void
    {
        $created = $this->delivery('delivery-id', 0);
        $created->subject = 'refund-id';

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame('refund-id', $data[0]['subject']);

                return true;
            }));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            $this->deliverySearchResult(),
            $this->deliverySearchResult($created)
        );

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        static::assertSame($created, $service->enqueue($this->order('https://app.example.test/webhook'), PaymentWebhookDispatcher::EVENT_REFUND_FINISHED, [], 'refund-id'));
    }

    public function testEnqueueReportsTheDeliveryAConcurrentAnnounceWonTheRaceFor(): void
    {
        $existing = $this->delivery('delivery-id', 0);

        $violation = static::createStub(UniqueConstraintViolationException::class);

        // the channel notification and the reconciliation may announce the same order at the same
        // moment: both lookups find nothing, one write wins the unique key, the loser reads it back
        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->willThrowException($violation);
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            $this->deliverySearchResult(),
            $this->deliverySearchResult($existing)
        );

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        // no exception: the delivery of the winner is reported as if this call had queued it
        static::assertSame($existing, $service->enqueue($this->order('https://app.example.test/webhook'), PaymentWebhookDispatcher::EVENT_ORDER_PAID));
    }

    public function testEnqueueRethrowsTheUniqueConstraintViolationWhenNoDeliveryWasWritten(): void
    {
        $violation = static::createStub(UniqueConstraintViolationException::class);

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->willThrowException($violation);
        // the violation was not caused by a parallel announce, so nothing can be read back
        $deliveryRepository->method('search')->willReturn($this->deliverySearchResult());

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        try {
            $service->enqueue($this->order('https://app.example.test/webhook'), PaymentWebhookDispatcher::EVENT_ORDER_PAID);
            static::fail('A unique constraint violation without a readable winner has to be reported.');
        } catch (UniqueConstraintViolationException $thrown) {
            // the very same exception, not a swallowed one
            static::assertSame($violation, $thrown);
        }
    }

    public function testDeliverMarksTheDeliveryAsSucceededOnA2xxResponse(): void
    {
        $service = $this->service(
            $this->expectedUpdate(function (array $row): void {
                static::assertSame(PaymentWebhookDeliveryEntity::STATUS_SUCCESS, $row['status']);
                static::assertSame(1, $row['attemptCount']);
                static::assertSame(200, $row['lastHttpStatus']);
                static::assertSame('ok', $row['lastResponse']);
                static::assertNull($row['nextRetryAt']);
                static::assertNotNull($row['succeededAt']);
            }),
            $this->appRepository($this->app()),
            $this->clientWithResponse(200, 'ok')
        );

        $service->deliver($this->delivery('delivery-id', 0));
    }

    public function testDeliverSchedulesARetryWhenTheResponseIsNotSuccessful(): void
    {
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = $this->service(
            $this->expectedUpdate(function (array $row): void {
                static::assertSame(PaymentWebhookDeliveryEntity::STATUS_FAILED, $row['status']);
                static::assertSame(1, $row['attemptCount']);
                static::assertSame(500, $row['lastHttpStatus']);
                static::assertNull($row['succeededAt']);

                $nextRetryAt = $row['nextRetryAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $nextRetryAt);
                static::assertSame('2024-01-01T00:01:00+00:00', $nextRetryAt->format(\DateTimeInterface::ATOM));
            }),
            $this->appRepository($this->app()),
            $this->clientWithResponse(500, 'boom'),
            $logger
        );

        $service->deliver($this->delivery('delivery-id', 0));
    }

    public function testDeliverGivesUpOnTheLastAttempt(): void
    {
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Giving up the payment webhook delivery to the app.',
                static::callback(static function (array $context): bool {
                    static::assertSame('delivery-id', $context['deliveryId']);
                    // the log has to name the order the delivery belongs to, so support can find it
                    static::assertSame('order-id', $context['paymentOrderId']);
                    static::assertArrayNotHasKey('paymentNo', $context);

                    return true;
                })
            );

        $service = $this->service(
            $this->expectedUpdate(function (array $row): void {
                static::assertSame(PaymentWebhookDeliveryEntity::STATUS_GIVING_UP, $row['status']);
                static::assertSame(PaymentWebhookDispatcher::MAX_ATTEMPTS, $row['attemptCount']);
                static::assertNull($row['nextRetryAt']);
            }),
            $this->appRepository($this->app()),
            $this->clientWithResponse(503, 'unavailable'),
            $logger
        );

        $service->deliver($this->delivery('delivery-id', PaymentWebhookDispatcher::MAX_ATTEMPTS - 1));
    }

    public function testDeliverStopsWhenTheAppDoesNotExistAnymore(): void
    {
        $updateRepository = static::createMock(EntityRepository::class);
        $updateRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                // the app is gone, so no attempt is made and the delivery is terminated
                static::assertNull($data[0]['lastHttpStatus']);
                static::assertSame(1, $data[0]['attemptCount']);
                static::assertSame('The payment app does not exist anymore.', $data[0]['lastResponse']);

                return true;
            }));

        $client = static::createMock(HttpClientInterface::class);
        $client->expects($this->never())->method('request');

        $service = $this->service($updateRepository, $this->appRepository(null), $client);

        $service->deliver($this->delivery('delivery-id', 0));
    }

    public function testDeliverRetriesWhenTheRequestItselfFails(): void
    {
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('connection reset'));

        $service = $this->service(
            $this->expectedUpdate(function (array $row): void {
                static::assertSame(PaymentWebhookDeliveryEntity::STATUS_FAILED, $row['status']);
                static::assertNull($row['lastHttpStatus']);
                static::assertSame('connection reset', $row['lastResponse']);
            }),
            $this->appRepository($this->app()),
            $client,
            $logger
        );

        $service->deliver($this->delivery('delivery-id', 0));
    }

    public function testRetryDueDeliversEveryDueDeliveryAndReturnsTheCount(): void
    {
        $due = $this->delivery('delivery-id', 0);

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->method('search')->willReturn($this->deliverySearchResult($due));
        $deliveryRepository->expects($this->once())->method('update');

        $client = static::createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->willReturn($this->response(200, 'ok'));

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()), $client);

        static::assertSame(1, $service->retryDue());
    }

    public function testRetryDueReturnsZeroWithoutDueDeliveries(): void
    {
        $deliveryRepository = static::createStub(EntityRepository::class);
        $deliveryRepository->method('search')->willReturn($this->deliverySearchResult());

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        static::assertSame(0, $service->retryDue());
    }

    public function testGetDeliveryThrowsWhenItDoesNotExist(): void
    {
        $deliveryRepository = static::createStub(EntityRepository::class);
        $deliveryRepository->method('search')->willReturn($this->deliverySearchResult());

        $service = $this->service($deliveryRepository, $this->appRepository($this->app()));

        $this->expectExceptionObject(PaymentException::webhookDeliveryNotFound('missing-id'));

        $service->getDelivery('missing-id');
    }

    /**
     * @param EntityRepository<PaymentWebhookDeliveryCollection> $deliveryRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     */
    private function service(
        EntityRepository $deliveryRepository,
        EntityRepository $appRepository,
        ?HttpClientInterface $client = null,
        ?LoggerInterface $logger = null
    ): PaymentWebhookDispatcher {
        return new PaymentWebhookDispatcher(
            $deliveryRepository,
            $appRepository,
            new PaymentWebhookSigner(new PaymentSignatureService(), new PaymentCredentialCipher('test-secret'), new MockClock('2024-01-01T00:00:00+00:00')),
            $client ?? static::createStub(HttpClientInterface::class),
            new MockClock('2024-01-01T00:00:00+00:00'),
            $logger ?? static::createStub(LoggerInterface::class)
        );
    }

    /**
     * @param callable(array<string, mixed>): void $assertion
     *
     * @return EntityRepository<PaymentWebhookDeliveryCollection>
     */
    private function expectedUpdate(callable $assertion): EntityRepository
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use ($assertion): bool {
                $assertion($data[0]);

                return true;
            }));

        return $repository;
    }

    private function order(?string $notifyUrl): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier('order-id');
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = self::APP_ID;
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->attach = 'attach-data';
        $order->stateId = 'state-opened';
        $order->notifyUrl = $notifyUrl;

        return $order;
    }

    private function delivery(string $id, int $attemptCount): PaymentWebhookDeliveryEntity
    {
        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier($id);
        $delivery->id = $id;
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = self::APP_ID;
        $delivery->event = PaymentWebhookDispatcher::EVENT_ORDER_PAID;
        $delivery->url = 'https://app.example.test/webhook';
        $delivery->payload = ['event' => PaymentWebhookDispatcher::EVENT_ORDER_PAID];
        $delivery->status = PaymentWebhookDeliveryEntity::STATUS_PENDING;
        $delivery->attemptCount = $attemptCount;

        return $delivery;
    }

    /**
     * @return EntitySearchResult<PaymentWebhookDeliveryCollection>
     */
    private function deliverySearchResult(PaymentWebhookDeliveryEntity ...$deliveries): EntitySearchResult
    {
        return new EntitySearchResult(
            'payment_webhook_delivery',
            \count($deliveries),
            new PaymentWebhookDeliveryCollection($deliveries),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(?PaymentAppEntity $app): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            $app === null ? 0 : 1,
            new PaymentAppCollection($app === null ? [] : [$app]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    private function app(): PaymentAppEntity
    {
        $cipher = new PaymentCredentialCipher('test-secret');

        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier(self::APP_ID);
        $app->id = self::APP_ID;
        $app->technicalName = 'demo-app';
        $app->appSecret = $cipher->encryptValue('app-secret');
        $app->active = true;

        return $app;
    }

    private function clientWithResponse(int $status, string $content): HttpClientInterface
    {
        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($this->response($status, $content));

        return $client;
    }

    private function response(int $status, string $content): ResponseInterface
    {
        $response = static::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getContent')->willReturn($content);

        return $response;
    }
}
