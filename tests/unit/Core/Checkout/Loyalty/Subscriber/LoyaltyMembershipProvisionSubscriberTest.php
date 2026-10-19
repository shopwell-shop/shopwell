<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Subscriber\LoyaltyMembershipProvisionSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyMembershipProvisionSubscriber::class)]
class LoyaltyMembershipProvisionSubscriberTest extends TestCase
{
    public function testSubscribesToCustomerWrites(): void
    {
        $subscriber = new LoyaltyMembershipProvisionSubscriber(static::createStub(LoyaltyPointService::class));

        static::assertSame(
            [CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten'],
            $subscriber->getSubscribedEvents()
        );
    }

    public function testProvisionsAMembershipForEveryWrittenCustomer(): void
    {
        $context = Context::createDefaultContext();
        $firstCustomerId = Uuid::randomHex();
        $secondCustomerId = Uuid::randomHex();

        $provisioned = [];

        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->exactly(2))
            ->method('provisionMembership')
            ->willReturnCallback(function (string $customerId, Context $receivedContext) use (&$provisioned, $context): void {
                static::assertSame($context, $receivedContext);

                $provisioned[] = $customerId;
            });

        $this->createSubscriber($loyaltyPointService)->onCustomerWritten(
            $this->createCustomerWrittenEvent($context, $firstCustomerId, $secondCustomerId)
        );

        static::assertSame([$firstCustomerId, $secondCustomerId], $provisioned);
    }

    public function testIgnoresWritesOutsideTheLiveVersion(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('provisionMembership');

        $this->createSubscriber($loyaltyPointService)->onCustomerWritten(
            $this->createCustomerWrittenEvent($context, Uuid::randomHex())
        );
    }

    private function createSubscriber(LoyaltyPointService $loyaltyPointService): LoyaltyMembershipProvisionSubscriber
    {
        return new LoyaltyMembershipProvisionSubscriber($loyaltyPointService);
    }

    private function createCustomerWrittenEvent(Context $context, string ...$customerIds): EntityWrittenEvent
    {
        $writeResults = [];

        foreach ($customerIds as $customerId) {
            $writeResults[] = new EntityWriteResult(
                $customerId,
                ['id' => $customerId],
                'customer',
                EntityWriteResult::OPERATION_INSERT
            );
        }

        return new EntityWrittenEvent('customer', $writeResults, $context);
    }
}
