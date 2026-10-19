<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopwell\Core\Checkout\Payment\Gateway\OrderPaymentPaidListener;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\StateMachine\Exception\IllegalTransitionException;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderPaymentPaidListener::class)]
class OrderPaymentPaidListenerTest extends TestCase
{
    public function testMovesTheTransactionToPaid(): void
    {
        $context = Context::createDefaultContext();

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler->expects($this->once())
            ->method('paid')
            ->with('order-transaction-id', $context);

        $listener = $this->listener($stateHandler);
        $listener(new PaymentOrderPaidEvent(PaymentGatewayFixture::paymentOrder(), $context));
    }

    public function testIgnoresAPaymentOfAnExternalApp(): void
    {
        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler->expects($this->never())->method('paid');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $listener = $this->listener($stateHandler, $logger);

        $listener(new PaymentOrderPaidEvent(
            PaymentGatewayFixture::paymentOrder(['orderTransactionId' => null]),
            Context::createDefaultContext()
        ));
    }

    public function testLogsARefusedTransitionInsteadOfThrowing(): void
    {
        $stateHandler = static::createStub(OrderTransactionStateHandler::class);
        $stateHandler->method('paid')->willThrowException(
            new IllegalTransitionException('cancelled', 'paid', ['open', 'in_progress'])
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::anything(),
                static::callback(static function (array $context): bool {
                    static::assertSame('PG-1', $context['paymentNo']);
                    static::assertSame('order-transaction-id', $context['orderTransactionId']);
                    static::assertSame('cancelled', $context['currentState']);

                    return true;
                })
            );

        $listener = $this->listener($stateHandler, $logger);

        // no exception: the announcement has to finish so the webhook of the app is queued
        $listener(new PaymentOrderPaidEvent(PaymentGatewayFixture::paymentOrder(), Context::createDefaultContext()));
    }

    public function testRethrowsEverythingElse(): void
    {
        $stateHandler = static::createStub(OrderTransactionStateHandler::class);
        $stateHandler->method('paid')->willThrowException(new \RuntimeException('the connection is gone'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $listener = $this->listener($stateHandler, $logger);

        $this->expectExceptionObject(new \RuntimeException('the connection is gone'));

        $listener(new PaymentOrderPaidEvent(PaymentGatewayFixture::paymentOrder(), Context::createDefaultContext()));
    }

    private function listener(
        OrderTransactionStateHandler $stateHandler,
        ?LoggerInterface $logger = null
    ): OrderPaymentPaidListener {
        return new OrderPaymentPaidListener(
            $stateHandler,
            $logger ?? static::createStub(LoggerInterface::class)
        );
    }
}
