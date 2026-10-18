<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferStateHandler::class)]
class PaymentTransferStateHandlerTest extends TestCase
{
    private StateMachineRegistry&MockObject $stateMachineRegistry;

    private PaymentTransferStateHandler $stateHandler;

    protected function setUp(): void
    {
        $this->stateMachineRegistry = static::createMock(StateMachineRegistry::class);
        $this->stateHandler = new PaymentTransferStateHandler($this->stateMachineRegistry);
    }

    public function testSubmit(): void
    {
        $this->expectTransition(PaymentTransferStates::ACTION_SUBMIT);
        $this->stateHandler->submit('transfer-id');
    }

    public function testProcess(): void
    {
        $this->expectTransition(PaymentTransferStates::ACTION_PROCESS);
        $this->stateHandler->process('transfer-id');
    }

    public function testAwaitConfirm(): void
    {
        $this->expectTransition(PaymentTransferStates::ACTION_AWAIT_CONFIRM);
        $this->stateHandler->awaitConfirm('transfer-id');
    }

    public function testSucceed(): void
    {
        $this->expectTransition(PaymentTransferStates::ACTION_SUCCEED);
        $this->stateHandler->succeed('transfer-id');
    }

    public function testFail(): void
    {
        $this->expectTransition(PaymentTransferStates::ACTION_FAIL);
        $this->stateHandler->fail('transfer-id');
    }

    public function testCancel(): void
    {
        $this->expectTransition(PaymentTransferStates::ACTION_CANCEL);
        $this->stateHandler->cancel('transfer-id');
    }

    public function testMarkReturned(): void
    {
        $this->expectTransition(PaymentTransferStates::ACTION_MARK_RETURNED);
        $this->stateHandler->markReturned('transfer-id');
    }

    private function expectTransition(string $action): void
    {
        $this->stateMachineRegistry
            ->expects($this->once())
            ->method('transition')
            ->with(
                new Transition(PaymentTransferEntity::ENTITY_NAME, 'transfer-id', $action, 'statusId'),
                static::isInstanceOf(Context::class)
            );
    }
}
