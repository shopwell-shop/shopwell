<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Subscription\PaymentCycleStateHandler;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleStates;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCycleStateHandler::class)]
class PaymentCycleStateHandlerTest extends TestCase
{
    private StateMachineRegistry&MockObject $stateMachineRegistry;

    private PaymentCycleStateHandler $stateHandler;

    protected function setUp(): void
    {
        $this->stateMachineRegistry = static::createMock(StateMachineRegistry::class);
        $this->stateHandler = new PaymentCycleStateHandler($this->stateMachineRegistry);
    }

    public function testSubmit(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_SUBMIT);
        $this->stateHandler->submit('cycle-id');
    }

    public function testProcess(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_PROCESS);
        $this->stateHandler->process('cycle-id');
    }

    public function testSucceed(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_SUCCEED);
        $this->stateHandler->succeed('cycle-id');
    }

    public function testFailRetryable(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE);
        $this->stateHandler->failRetryable('cycle-id');
    }

    public function testFailFinal(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL);
        $this->stateHandler->failFinal('cycle-id');
    }

    public function testMarkUnknown(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_MARK_UNKNOWN);
        $this->stateHandler->markUnknown('cycle-id');
    }

    public function testReschedule(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_RESCHEDULE);
        $this->stateHandler->reschedule('cycle-id');
    }

    public function testRefund(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_REFUND);
        $this->stateHandler->refund('cycle-id');
    }

    public function testRefundPartially(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_REFUND_PARTIALLY);
        $this->stateHandler->refundPartially('cycle-id');
    }

    public function testClose(): void
    {
        $this->transition(PaymentSubscriptionCycleStates::ACTION_CLOSE);
        $this->stateHandler->close('cycle-id');
    }

    private function transition(string $action): void
    {
        $this->stateMachineRegistry
            ->expects($this->once())
            ->method('transition')
            ->with(
                new Transition(PaymentSubscriptionCycleEntity::ENTITY_NAME, 'cycle-id', $action, 'stateId'),
                static::isInstanceOf(Context::class)
            );
    }
}
