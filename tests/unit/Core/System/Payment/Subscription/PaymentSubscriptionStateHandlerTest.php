<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStateHandler;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStates;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentSubscriptionStateHandler::class)]
class PaymentSubscriptionStateHandlerTest extends TestCase
{
    private StateMachineRegistry&MockObject $stateMachineRegistry;

    private PaymentSubscriptionStateHandler $stateHandler;

    protected function setUp(): void
    {
        $this->stateMachineRegistry = static::createMock(StateMachineRegistry::class);
        $this->stateHandler = new PaymentSubscriptionStateHandler($this->stateMachineRegistry);
    }

    public function testTrial(): void
    {
        $this->transition(PaymentSubscriptionStates::ACTION_TRIAL);
        $this->stateHandler->trial('subscription-id');
    }

    public function testActivate(): void
    {
        $this->transition(PaymentSubscriptionStates::ACTION_ACTIVATE);
        $this->stateHandler->activate('subscription-id');
    }

    public function testFail(): void
    {
        $this->transition(PaymentSubscriptionStates::ACTION_FAIL);
        $this->stateHandler->fail('subscription-id');
    }

    public function testSuspend(): void
    {
        $this->transition(PaymentSubscriptionStates::ACTION_SUSPEND);
        $this->stateHandler->suspend('subscription-id');
    }

    public function testCancel(): void
    {
        $this->transition(PaymentSubscriptionStates::ACTION_CANCEL);
        $this->stateHandler->cancel('subscription-id');
    }

    public function testExpire(): void
    {
        $this->transition(PaymentSubscriptionStates::ACTION_EXPIRE);
        $this->stateHandler->expire('subscription-id');
    }

    private function transition(string $action): void
    {
        $this->stateMachineRegistry
            ->expects($this->once())
            ->method('transition')
            ->with(
                new Transition(PaymentSubscriptionEntity::ENTITY_NAME, 'subscription-id', $action, 'statusId'),
                static::isInstanceOf(Context::class)
            );
    }
}
