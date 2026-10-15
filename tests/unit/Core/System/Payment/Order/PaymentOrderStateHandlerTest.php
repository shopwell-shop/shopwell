<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderStateHandler::class)]
class PaymentOrderStateHandlerTest extends TestCase
{
    private StateMachineRegistry&MockObject $stateMachineRegistry;

    private PaymentOrderStateHandler $stateHandler;

    protected function setUp(): void
    {
        $this->stateMachineRegistry = static::createMock(StateMachineRegistry::class);
        $this->stateHandler = new PaymentOrderStateHandler($this->stateMachineRegistry);
    }

    public function testOpen(): void
    {
        $this->transition(PaymentOrderStates::ACTION_PROCESS);
        $this->stateHandler->open('order-id');
    }

    public function testPay(): void
    {
        $this->transition(PaymentOrderStates::ACTION_PAY);
        $this->stateHandler->pay('order-id');
    }

    public function testFail(): void
    {
        $this->transition(PaymentOrderStates::ACTION_FAIL);
        $this->stateHandler->fail('order-id');
    }

    public function testClose(): void
    {
        $this->transition(PaymentOrderStates::ACTION_CLOSE);
        $this->stateHandler->close('order-id');
    }

    public function testExpire(): void
    {
        $this->transition(PaymentOrderStates::ACTION_EXPIRE);
        $this->stateHandler->expire('order-id');
    }

    public function testRefund(): void
    {
        $this->transition(PaymentOrderStates::ACTION_REFUND);
        $this->stateHandler->refund('order-id');
    }

    public function testRefundPartially(): void
    {
        $this->transition(PaymentOrderStates::ACTION_REFUND_PARTIALLY);
        $this->stateHandler->refundPartially('order-id');
    }

    private function transition(string $action): void
    {
        $this->stateMachineRegistry
            ->expects($this->once())
            ->method('transition')
            ->with(
                new Transition(PaymentOrderEntity::ENTITY_NAME, 'order-id', $action, 'stateId'),
                static::isInstanceOf(Context::class)
            );
    }
}
