<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletAccountProvisionSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletAccountProvisionSubscriber::class)]
class WalletAccountProvisionSubscriberTest extends TestCase
{
    public function testSubscribesToCustomerWrites(): void
    {
        $subscriber = new WalletAccountProvisionSubscriber(static::createStub(WalletService::class));

        static::assertSame(
            [CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten'],
            $subscriber->getSubscribedEvents()
        );
    }

    public function testProvisionsAWalletForEveryWrittenCustomer(): void
    {
        $context = Context::createDefaultContext();
        $firstCustomerId = Uuid::randomHex();
        $secondCustomerId = Uuid::randomHex();

        $provisioned = [];

        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->exactly(2))
            ->method('provisionAccount')
            ->willReturnCallback(function (string $customerId, Context $receivedContext) use (&$provisioned, $context): void {
                static::assertSame($context, $receivedContext);

                $provisioned[] = $customerId;
            });

        $this->createSubscriber($walletService)->onCustomerWritten(
            $this->createCustomerWrittenEvent($context, $firstCustomerId, $secondCustomerId)
        );

        static::assertSame([$firstCustomerId, $secondCustomerId], $provisioned);
    }

    public function testIgnoresWritesOutsideTheLiveVersion(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->never())->method('provisionAccount');

        $this->createSubscriber($walletService)->onCustomerWritten(
            $this->createCustomerWrittenEvent($context, Uuid::randomHex())
        );
    }

    private function createSubscriber(WalletService $walletService): WalletAccountProvisionSubscriber
    {
        return new WalletAccountProvisionSubscriber($walletService);
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
