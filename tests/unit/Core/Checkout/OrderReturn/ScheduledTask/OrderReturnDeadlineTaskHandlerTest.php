<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\ScheduledTask\OrderReturnDeadlineTaskHandler;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * A deadline never decides against the customer: an approval the shop did not decide on is approved
 * automatically or only remembered, while a parcel that was never announced is closed.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnDeadlineTaskHandler::class)]
class OrderReturnDeadlineTaskHandlerTest extends TestCase
{
    public function testAnOverdueRequestIsApprovedWhenTheShopWantsThat(): void
    {
        $orderReturn = $this->orderReturn(OrderReturnStates::STATE_REQUESTED);
        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())->method('approve')->with($orderReturn->id);
        $settings = static::createStub(OrderReturnSettings::class);
        $settings->method('autoApprove')->willReturn(true);

        $this->handler($service, $settings, [$orderReturn])->run();
    }

    public function testAnOverdueRequestIsOnlyRememberedOtherwise(): void
    {
        $orderReturn = $this->orderReturn(OrderReturnStates::STATE_REQUESTED);
        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->never())->method('approve');
        $service->expects($this->once())
            ->method('addMessage')
            ->with($orderReturn->id, static::anything(), OrderReturnEntity::AUTHOR_SYSTEM);
        $settings = static::createStub(OrderReturnSettings::class);
        $settings->method('autoApprove')->willReturn(false);

        $repository = $this->repository([$orderReturn]);
        $this->handler($service, $settings, [], $repository)->run();

        static::assertSame(
            [['id' => $orderReturn->id, 'deadlineAt' => null]],
            $repository->updates[0]
        );
    }

    public function testAnOverdueOpenReturnIsClosedByTheClock(): void
    {
        $orderReturn = $this->orderReturn(OrderReturnStates::STATE_OPEN);
        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())->method('cancel')->with($orderReturn->id);
        $settings = static::createStub(OrderReturnSettings::class);

        $this->handler($service, $settings, [$orderReturn])->run();
    }

    public function testOneBrokenReturnDoesNotStopTheOthers(): void
    {
        $broken = $this->orderReturn(OrderReturnStates::STATE_OPEN, 'RT-BROKEN');
        $next = $this->orderReturn(OrderReturnStates::STATE_OPEN, 'RT-NEXT');

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->exactly(2))
            ->method('cancel')
            ->willReturnCallback(static function (string $id) use ($broken, $next): OrderReturnEntity {
                if ($id === $broken->id) {
                    throw new \RuntimeException('the gateway is down');
                }

                return $next;
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('could not be processed'),
                static::callback(static fn (array $context): bool => ($context['returnNumber'] ?? null) === 'RT-BROKEN'
                    && ($context['message'] ?? null) === 'the gateway is down')
            );

        $this->handler($service, static::createStub(OrderReturnSettings::class), [$broken, $next], null, $logger)->run();
    }

    public function testOnlyReturnsWhoseDeadlineHasPassedArePickedUp(): void
    {
        $clock = new MockClock('2026-10-21 09:30:15');
        $captured = null;

        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use (&$captured): OrderReturnCollection {
                $captured = $criteria;

                return new OrderReturnCollection();
            },
        ]);

        $handler = new OrderReturnDeadlineTaskHandler(
            new StaticEntityRepository([]),
            static::createStub(LoggerInterface::class),
            $repository,
            static::createStub(OrderReturnService::class),
            static::createStub(OrderReturnSettings::class),
            $clock,
        );

        $handler->run();

        static::assertInstanceOf(Criteria::class, $captured);
        $filter = $captured->getFilters()[0] ?? null;
        static::assertInstanceOf(RangeFilter::class, $filter);
        static::assertSame('deadlineAt', $filter->getField());
        static::assertSame(
            $clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $filter->getParameters()[RangeFilter::LTE] ?? null
        );
        static::assertTrue($captured->hasAssociation('stateMachineState'));
    }

    /**
     * @param list<OrderReturnEntity> $orderReturns
     * @param StaticEntityRepository<OrderReturnCollection>|null $repository
     */
    private function handler(
        OrderReturnService $service,
        OrderReturnSettings $settings,
        array $orderReturns,
        ?StaticEntityRepository $repository = null,
        ?LoggerInterface $logger = null
    ): OrderReturnDeadlineTaskHandler {
        return new OrderReturnDeadlineTaskHandler(
            new StaticEntityRepository([]),
            $logger ?? static::createStub(LoggerInterface::class),
            $repository ?? $this->repository($orderReturns),
            $service,
            $settings,
            new MockClock('2026-10-21 09:30:15'),
        );
    }

    /**
     * @param list<OrderReturnEntity> $orderReturns
     *
     * @return StaticEntityRepository<OrderReturnCollection>
     */
    private function repository(array $orderReturns): StaticEntityRepository
    {
        return new StaticEntityRepository([new OrderReturnCollection($orderReturns)]);
    }

    private function orderReturn(string $state, string $returnNumber = 'RT-1'): OrderReturnEntity
    {
        $orderReturn = (new OrderReturnEntity())->assign([
            'id' => 'return-id-' . $state . '-' . $returnNumber,
            'returnNumber' => $returnNumber,
            'salesChannelId' => 'sales-channel-id',
            'stateMachineState' => (new StateMachineStateEntity())->assign(['technicalName' => $state]),
        ]);
        $orderReturn->setUniqueIdentifier($orderReturn->id);

        return $orderReturn;
    }
}
