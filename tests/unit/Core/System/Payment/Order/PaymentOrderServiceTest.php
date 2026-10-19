<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderService::class)]
class PaymentOrderServiceTest extends TestCase
{
    private const CREATED_STATE = 'state-created';

    private const NOW = '2024-01-01T00:00:00+00:00';

    public function testIsPayableForAnOpenOrder(): void
    {
        static::assertTrue($this->service($this->orderRepository())->isPayable($this->order()));
    }

    public function testIsNotPayableWhenTheOrderWasPaid(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]);

        static::assertFalse($this->service($this->orderRepository())->isPayable($order));
    }

    public function testIsNotPayableWhenTheOrderWasClosed(): void
    {
        $order = $this->order(['closedAt' => new \DateTimeImmutable(self::NOW)]);

        static::assertFalse($this->service($this->orderRepository())->isPayable($order));
    }

    public function testIsCreatedWhenTheOrderIsInTheInitialState(): void
    {
        static::assertTrue($this->service($this->orderRepository())->isCreated($this->order()));
    }

    public function testIsNotCreatedWhenTheOrderMovedOn(): void
    {
        $order = $this->order(['stateId' => 'state-opened']);

        static::assertFalse($this->service($this->orderRepository())->isCreated($order));
    }

    public function testGetByIdReturnsTheOrder(): void
    {
        $order = $this->order();

        $service = $this->service($this->orderRepository([$order]));

        static::assertSame($order, $service->getById('order-id'));
    }

    public function testGetByIdThrowsWhenTheOrderDoesNotExist(): void
    {
        $service = $this->service($this->orderRepository());

        $this->expectExceptionObject(PaymentException::orderNotFound('missing-id'));

        $service->getById('missing-id');
    }

    public function testFindByPaymentNoReturnsTheOrder(): void
    {
        $order = $this->order();

        static::assertSame($order, $this->service($this->orderRepository([$order]))->findByPaymentNo('PG0001'));
    }

    public function testFindByPaymentNoReturnsNullWhenTheOrderIsUnknown(): void
    {
        static::assertNull($this->service($this->orderRepository())->findByPaymentNo('PG0001'));
    }

    public function testGetByPaymentNoReturnsTheOrderOfTheApp(): void
    {
        $order = $this->order();
        $service = $this->service($this->orderRepository([$order]));

        static::assertSame($order, $service->getByPaymentNo('PG0001', $this->app('app-id')));
    }

    public function testGetByPaymentNoThrowsWhenTheOrderDoesNotExist(): void
    {
        $service = $this->service($this->orderRepository());

        $this->expectExceptionObject(PaymentException::orderNotFound('PG0001'));

        $service->getByPaymentNo('PG0001', $this->app('app-id'));
    }

    public function testGetByPaymentNoThrowsWhenTheOrderBelongsToAnotherApp(): void
    {
        $order = $this->order();
        $service = $this->service($this->orderRepository([$order]));

        $this->expectExceptionObject(PaymentException::foreignApp('other-app', 'PG0001'));

        $service->getByPaymentNo('PG0001', $this->app('other-app-id', 'other-app'));
    }

    public function testCloseTransitionsAndStampsTheClosedAt(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('order-id', $data[0]['id']);

                $closedAt = $data[0]['closedAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $closedAt);
                static::assertSame(self::NOW, $closedAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_CLOSE));

        $this->service($repository, null, null, $stateMachine)->close($this->order());
    }

    public function testCloseThrowsWhenTheOrderIsAlreadyClosed(): void
    {
        $order = $this->order(['closedAt' => new \DateTimeImmutable(self::NOW)]);

        $this->expectExceptionObject(PaymentException::orderAlreadyClosed('PG0001'));

        $this->service($this->orderRepository())->close($order);
    }

    /**
     * A paid order must not be reported as merely closed: the app has to book the payment, which is
     * a different reaction than retrying a close.
     */
    public function testCloseThrowsAlreadyPaidWhenTheOrderIsAlreadyPaid(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]);

        $this->expectExceptionObject(PaymentException::orderAlreadyPaid('PG0001'));

        $this->service($this->orderRepository())->close($order);
    }

    public function testExpireThrowsAlreadyPaidWhenTheOrderIsAlreadyPaid(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]);

        $this->expectExceptionObject(PaymentException::orderAlreadyPaid('PG0001'));

        $this->service($this->orderRepository())->expire($order);
    }

    public function testExpireThrowsWhenTheOrderIsAlreadyClosed(): void
    {
        $order = $this->order(['closedAt' => new \DateTimeImmutable(self::NOW)]);

        $this->expectExceptionObject(PaymentException::orderAlreadyClosed('PG0001'));

        $this->service($this->orderRepository())->expire($order);
    }

    public function testMarkOpenedTransitionsARequestedOrder(): void
    {
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_PROCESS));

        $this->service($this->orderRepository(), null, null, $stateMachine)->markOpened($this->order());
    }

    public function testMarkOpenedDoesNothingWhenTheOrderWasAlreadyOpened(): void
    {
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $order = $this->order(['stateId' => 'state-opened']);

        $this->service($this->orderRepository(), null, null, $stateMachine)->markOpened($order);
    }

    public function testMarkPaidTransitionsAndPersistsTheConfirmation(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(1000, $data[0]['paidAmount']);
                static::assertSame('channel-trade-1', $data[0]['outPaymentNo']);
                static::assertSame(['trade' => 'raw'], $data[0]['channelData']);

                $paidAt = $data[0]['paidAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
                static::assertSame(self::NOW, $paidAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_PAY));

        $this->service($repository, null, null, $stateMachine)
            ->markPaid($this->order(), 1000, 'channel-trade-1', ['trade' => 'raw']);
    }

    public function testMarkFailedTransitionsTheOrder(): void
    {
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_FAIL));

        $this->service($this->orderRepository(), null, null, $stateMachine)->markFailed($this->order());
    }

    public function testApplyRefundKeepsAPartiallyRefundedOrderOpen(): void
    {
        $refunded = $this->order(['refundedAmount' => 300, 'paidAmount' => 1000, 'stateId' => 'state-refunded_partially']);

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(300, $data[0]['refundedAmount']);

                return true;
            }));
        $repository->method('search')->willReturn($this->searchResult([$refunded]));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND_PARTIALLY));

        $order = $this->order(['paidAmount' => 1000]);
        $result = $this->service($repository, null, null, $stateMachine)->applyRefund($order, 300);

        static::assertSame($refunded, $result);
    }

    public function testApplyRefundClosesAFullyRefundedOrder(): void
    {
        $refunded = $this->order(['refundedAmount' => 1000, 'paidAmount' => 1000, 'stateId' => 'state-refunded']);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$refunded]));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND));

        $order = $this->order(['paidAmount' => 1000]);
        $result = $this->service($repository, null, null, $stateMachine)->applyRefund($order, 1000);

        static::assertSame(1000, $result->refundedAmount);
    }

    public function testExpireDueExpiresOnlyThePayableOrders(): void
    {
        $payable = $this->order();
        $alreadyPaid = $this->order(['id' => 'order-2', 'paidAt' => new \DateTimeImmutable(self::NOW)]);

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$payable, $alreadyPaid]));
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('order-id', $data[0]['id']);

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_EXPIRE));

        static::assertSame(1, $this->service($repository, null, null, $stateMachine)->expireDue());
    }

    public function testExpireDueReturnsZeroWhenNothingIsDue(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([]));
        $repository->expects($this->never())->method('update');

        static::assertSame(0, $this->service($repository)->expireDue());
    }

    /**
     * The channel already holds the order, so the payer may still pay it - only a notification or the
     * reconciliation may end it.
     */
    public function testExpireThrowsWhenTheOrderWasHandedToTheChannel(): void
    {
        $order = $this->order(['stateId' => 'state-opened']);

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $this->expectExceptionObject(PaymentException::orderNotExpirable('PG0001'));

        $this->service($repository, null, null, $stateMachine)->expire($order);
    }

    public function testCreateReturnsTheExistingOrderForAKnownOutTradeNo(): void
    {
        $existing = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$existing]));
        $repository->expects($this->never())->method('create');

        $result = $this->service($repository, $this->workingConfigResolver())
            ->create($this->app('app-id'), $this->request());

        static::assertSame($existing, $result);
    }

    /**
     * An app that retries with the same idempotency key but a fresh trade number violates the second
     * unique key of the table - the order it already created is the answer, not a conflict.
     */
    public function testCreateHandsOutTheOrderOfTheSameIdempotencyKey(): void
    {
        $existing = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(3))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use ($existing): EntitySearchResult {
                $fields = array_map(
                    static fn ($filter): string => $filter instanceof EqualsFilter ? $filter->getField() : '',
                    $criteria->getFilters()
                );

                return $fields === ['paymentAppId', 'idempotencyKey']
                    ? $this->searchResult([$existing])
                    : $this->searchResult([]);
            });
        $repository->expects($this->once())
            ->method('create')
            ->willThrowException(static::createStub(UniqueConstraintViolationException::class));

        $request = new PaymentOrderRequest(
            outTradeNo: 'app-trade-2',
            channel: 'alipay_h5',
            amount: 1000,
            currency: 'CNY',
            subject: 'An order',
            idempotencyKey: 'idem-1',
        );

        static::assertSame(
            $existing,
            $this->service($repository, $this->workingConfigResolver())->create($this->app('app-id'), $request)
        );
    }

    /**
     * Neither key reads a row back, so the payment number of the conflicting order is unknown - the
     * exception has to carry the out trade number the caller sent, not a fabricated payment number.
     */
    public function testCreateReportsTheOutTradeNoWhenTheConflictingOrderCannotBeReadBack(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([]));
        $repository->expects($this->once())
            ->method('create')
            ->willThrowException(static::createStub(UniqueConstraintViolationException::class));

        try {
            $this->service($repository, $this->workingConfigResolver())->create($this->app('app-id'), $this->request());
            static::fail('the conflict has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::ORDER_CONFLICT, $exception->getErrorCode());
            static::assertSame('app-trade-1', $exception->getParameter('outTradeNo'));
            static::assertNull($exception->getParameter('paymentNo'));
        }
    }

    public function testCreatePersistsANewOrder(): void
    {
        $created = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                $row = $data[0];

                static::assertStringStartsWith('PG20240101000000', $row['paymentNo']);
                static::assertSame('app-trade-1', $row['outTradeNo']);
                static::assertSame('app-id', $row['paymentAppId']);
                static::assertSame('alipay', $row['providerTechnicalName']);
                static::assertSame('alipay_h5', $row['channelTechnicalName']);
                static::assertNull($row['channelParams']);
                static::assertSame(1000, $row['amount']);
                // a fresh order is neither paid nor refunded, the create has to say so
                static::assertSame(0, $row['paidAmount']);
                static::assertSame(0, $row['refundedAmount']);
                static::assertSame('CNY', $row['currency']);
                static::assertSame('An order', $row['subject']);
                static::assertSame(self::CREATED_STATE, $row['stateId']);
                static::assertSame('https://notify.example.test', $row['notifyUrl']);
                static::assertSame('https://return.example.test', $row['returnUrl']);

                $expireAt = $row['expireAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $expireAt);
                static::assertSame('2024-01-01T00:30:00+00:00', $expireAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $result = $this->service($repository, $this->workingConfigResolver())
            ->create($this->app('app-id'), $this->request());

        static::assertSame($created, $result);
    }

    public function testCreateHonoursTheRequestedExpiry(): void
    {
        $created = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                $expireAt = $data[0]['expireAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $expireAt);
                static::assertSame('2024-01-01T00:10:00+00:00', $expireAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $request = new PaymentOrderRequest(
            outTradeNo: 'app-trade-1',
            channel: 'alipay_h5',
            amount: 1000,
            currency: 'CNY',
            subject: 'An order',
            expireMinutes: 10,
        );

        $this->service($repository, $this->workingConfigResolver())->create($this->app('app-id'), $request);
    }

    /**
     * The order and its transaction are the only callers that reference their own order.
     */
    public function testCreatePersistsTheReferencesOfTheSelfOperatedCheckout(): void
    {
        $created = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('checkout-order-id', $data[0]['orderId']);
                // both references are versioned, the composite foreign keys need the version too
                static::assertSame('checkout-order-version', $data[0]['orderVersionId']);
                static::assertSame('checkout-transaction-id', $data[0]['orderTransactionId']);
                static::assertSame('checkout-transaction-version', $data[0]['orderTransactionVersionId']);

                return true;
            }));

        $this->service($repository, $this->workingConfigResolver())
            ->create(
                $this->app('app-id'),
                $this->request(),
                $this->checkoutOrder('checkout-order-version'),
                $this->checkoutTransaction('checkout-transaction-version')
            );
    }

    /**
     * A live order that was built in memory carries no version of its own - the reference still has
     * to name one, and the live version is the one the checkout is working on.
     */
    public function testCreateFallsBackToTheLiveVersionWhenTheReferencesCarryNone(): void
    {
        $created = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(Defaults::LIVE_VERSION, $data[0]['orderVersionId']);
                static::assertSame(Defaults::LIVE_VERSION, $data[0]['orderTransactionVersionId']);

                return true;
            }));

        $this->service($repository, $this->workingConfigResolver())
            ->create($this->app('app-id'), $this->request(), $this->checkoutOrder(), $this->checkoutTransaction());
    }

    /**
     * An external app brings its own trade number, never an order of the shop - the columns stay empty
     * instead of being written as null.
     */
    public function testCreateLeavesTheReferencesOutForAnExternalApp(): void
    {
        $created = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                static::assertArrayNotHasKey('orderId', $data[0]);
                static::assertArrayNotHasKey('orderVersionId', $data[0]);
                static::assertArrayNotHasKey('orderTransactionId', $data[0]);
                static::assertArrayNotHasKey('orderTransactionVersionId', $data[0]);

                return true;
            }));

        $this->service($repository, $this->workingConfigResolver())->create($this->app('app-id'), $this->request());
    }

    public function testFindLatestByOrderTransactionAsksForTheNewestPaymentOfTheTransaction(): void
    {
        $latest = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->with(static::callback(function (Criteria $criteria): bool {
                // the caller resolves the channel credentials of the payment, which needs the app
                static::assertTrue($criteria->hasAssociation('paymentApp'));
                // reopening the payment rebuilds the hand over from the answer of the last capture
                static::assertTrue($criteria->hasAssociation('transactions'));
                static::assertSame(1, $criteria->getLimit());

                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('orderTransactionId', $filters[0]->getField());
                static::assertSame('checkout-transaction-id', $filters[0]->getValue());

                // a transaction may have more than one payment order, the newest one is in charge
                $sorting = $criteria->getSorting();
                static::assertCount(1, $sorting);
                static::assertSame('createdAt', $sorting[0]->getField());
                static::assertSame(FieldSorting::DESCENDING, $sorting[0]->getDirection());

                return true;
            }))
            ->willReturn($this->searchResult([$latest]));

        static::assertSame(
            $latest,
            $this->service($repository)->findLatestByOrderTransaction('checkout-transaction-id')
        );
    }

    public function testFindLatestByOrderTransactionReturnsNullWhenTheTransactionIsUnknown(): void
    {
        static::assertNull($this->service($this->orderRepository())->findLatestByOrderTransaction('checkout-transaction-id'));
    }

    /**
     * The registry is handed to the real state handler, so the transitions the tests assert on stay the
     * ones the machine really receives - the handler is what names the actions.
     *
     * @param EntityRepository<PaymentOrderCollection> $repository
     */
    private function service(
        EntityRepository $repository,
        ?PaymentConfigResolver $configResolver = null,
        ?InitialStateIdLoader $initialStateIdLoader = null,
        ?StateMachineRegistry $stateMachineRegistry = null
    ): PaymentOrderService {
        return new PaymentOrderService(
            $repository,
            $configResolver ?? $this->inertConfigResolver(),
            $initialStateIdLoader ?? $this->initialStateIdLoader(),
            new PaymentOrderStateHandler($stateMachineRegistry ?? static::createStub(StateMachineRegistry::class)),
            new MockClock(self::NOW)
        );
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn(self::CREATED_STATE);

        return $loader;
    }

    private function inertConfigResolver(): PaymentConfigResolver
    {
        $providerRegistry = new PaymentProviderRegistry([]);

        return new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher('test-secret')
        );
    }

    private function workingConfigResolver(): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher('test-secret');

        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = 'app-id';
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
        $appProvider->mode = PaymentAppProviderEntity::MODE_OWN;
        $appProvider->credentials = $cipher->encrypt(['app_id' => 'own-app-id']);

        $appChannel = new PaymentAppChannelEntity();
        $appChannel->setUniqueIdentifier('app-channel-id');
        $appChannel->paymentAppId = 'app-id';
        $appChannel->channelTechnicalName = 'alipay_h5';
        $appChannel->enabled = true;
        $appChannel->params = $cipher->encrypt(['appid' => 'own-appid']);

        $channelConfig = new PaymentChannelConfigEntity();
        $channelConfig->setUniqueIdentifier('channel-config-id');
        $channelConfig->providerTechnicalName = 'alipay';
        $channelConfig->channelTechnicalName = 'alipay_h5';
        $channelConfig->enabled = true;
        $channelConfig->params = $cipher->encrypt(['appid' => 'platform-appid']);

        $appProviderRepository = static::createStub(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn($this->searchResult([$appProvider], 'payment_app_provider'));

        $appChannelRepository = static::createStub(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn($this->searchResult([$appChannel], 'payment_app_channel'));

        $channelConfigRepository = static::createStub(EntityRepository::class);
        $channelConfigRepository->method('search')->willReturn($this->searchResult([$channelConfig], 'payment_channel_config'));

        return new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            $appProviderRepository,
            static::createStub(EntityRepository::class),
            $appChannelRepository,
            $channelConfigRepository,
            $cipher
        );
    }

    /**
     * @param list<PaymentOrderEntity> $orders
     *
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function orderRepository(array $orders = []): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult($orders));

        return $repository;
    }

    /**
     * @param list<Entity> $entities
     *
     * @return ($entityName is PaymentOrderEntity::ENTITY_NAME ? EntitySearchResult<PaymentOrderCollection> : EntitySearchResult<EntityCollection<Entity>>)
     */
    private function searchResult(array $entities, string $entityName = PaymentOrderEntity::ENTITY_NAME): EntitySearchResult
    {
        if ($entityName !== PaymentOrderEntity::ENTITY_NAME) {
            return new EntitySearchResult(
                $entityName,
                \count($entities),
                new EntityCollection($entities),
                null,
                new Criteria(),
                Context::createDefaultContext()
            );
        }

        /** @var list<PaymentOrderEntity> $entities */
        return new EntitySearchResult(
            $entityName,
            \count($entities),
            new PaymentOrderCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier((string) ($overrides['id'] ?? 'order-id'));
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = self::CREATED_STATE;

        if (\array_key_exists('id', $overrides)) {
            $order->id = (string) $overrides['id'];
        }

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('paidAt', $overrides)) {
            $paidAt = $overrides['paidAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
            $order->paidAt = $paidAt;
        }

        if (\array_key_exists('closedAt', $overrides)) {
            $closedAt = $overrides['closedAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $closedAt);
            $order->closedAt = $closedAt;
        }

        if (\array_key_exists('paidAmount', $overrides)) {
            $order->paidAmount = (int) $overrides['paidAmount'];
        }

        if (\array_key_exists('refundedAmount', $overrides)) {
            $order->refundedAmount = (int) $overrides['refundedAmount'];
        }

        return $order;
    }

    private function checkoutOrder(?string $versionId = null): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('checkout-order-id');

        if ($versionId !== null) {
            $order->setVersionId($versionId);
        }

        return $order;
    }

    private function checkoutTransaction(?string $versionId = null): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('checkout-transaction-id');

        if ($versionId !== null) {
            $transaction->setVersionId($versionId);
        }

        return $transaction;
    }

    private function app(string $id, string $technicalName = 'demo-app'): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier($id);
        $app->id = $id;
        $app->technicalName = $technicalName;
        $app->appSecret = 'secret';
        $app->active = true;

        return $app;
    }

    private function request(): PaymentOrderRequest
    {
        return new PaymentOrderRequest(
            outTradeNo: 'app-trade-1',
            channel: 'alipay_h5',
            amount: 1000,
            currency: 'CNY',
            subject: 'An order',
            notifyUrl: 'https://notify.example.test',
            returnUrl: 'https://return.example.test',
        );
    }
}
