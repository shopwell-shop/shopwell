<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order\Refund;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStates;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundStateHandler::class)]
class PaymentRefundStateHandlerTest extends TestCase
{
    private StateMachineRegistry&MockObject $stateMachineRegistry;

    private PaymentRefundStateHandler $stateHandler;

    protected function setUp(): void
    {
        $this->stateMachineRegistry = static::createMock(StateMachineRegistry::class);
        $this->stateHandler = new PaymentRefundStateHandler($this->stateMachineRegistry);
    }

    public function testProcess(): void
    {
        $this->transition(PaymentRefundStates::ACTION_PROCESS);
        $this->stateHandler->process('refund-id');
    }

    public function testFinish(): void
    {
        $this->transition(PaymentRefundStates::ACTION_FINISH);
        $this->stateHandler->finish('refund-id');
    }

    public function testFail(): void
    {
        $this->transition(PaymentRefundStates::ACTION_FAIL);
        $this->stateHandler->fail('refund-id');
    }

    private function transition(string $action): void
    {
        $this->stateMachineRegistry
            ->expects($this->once())
            ->method('transition')
            ->with(
                new Transition(PaymentRefundEntity::ENTITY_NAME, 'refund-id', $action, 'stateId'),
                static::isInstanceOf(Context::class)
            );
    }
}
