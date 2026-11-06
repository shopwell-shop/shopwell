<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyAccountService;
use Shopwell\Core\Checkout\Loyalty\Subscriber\LoyaltyAccountProvisionSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyAccountProvisionSubscriber::class)]
class LoyaltyAccountProvisionSubscriberTest extends TestCase
{
    public function testSubscribesToCustomerWrites(): void
    {
        $subscriber = new LoyaltyAccountProvisionSubscriber(static::createStub(LoyaltyAccountService::class));

        static::assertSame(
            [CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten'],
            $subscriber->getSubscribedEvents()
        );
    }

    public function testOpensAnAccountForEveryWrittenCustomer(): void
    {
        $context = Context::createDefaultContext();
        $firstCustomerId = Uuid::randomHex();
        $secondCustomerId = Uuid::randomHex();

        $opened = [];

        $accounts = static::createMock(LoyaltyAccountService::class);
        $accounts->expects($this->exactly(2))
            ->method('open')
            ->willReturnCallback(function (string $customerId, Context $receivedContext) use (&$opened, $context): void {
                static::assertSame($context, $receivedContext);

                $opened[] = $customerId;
            });

        $this->createSubscriber($accounts)->onCustomerWritten(
            $this->createCustomerWrittenEvent($context, $firstCustomerId, $secondCustomerId)
        );

        static::assertSame([$firstCustomerId, $secondCustomerId], $opened);
    }

    public function testIgnoresWritesOutsideTheLiveVersion(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $accounts = static::createMock(LoyaltyAccountService::class);
        $accounts->expects($this->never())->method('open');

        $this->createSubscriber($accounts)->onCustomerWritten(
            $this->createCustomerWrittenEvent($context, Uuid::randomHex())
        );
    }

    private function createSubscriber(LoyaltyAccountService $accounts): LoyaltyAccountProvisionSubscriber
    {
        return new LoyaltyAccountProvisionSubscriber($accounts);
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
