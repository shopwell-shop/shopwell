<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStateHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * Every transition of a return funnels through this handler, so the action names of the machine are
 * spelled out exactly once. The context is passed through as well: a decision of an administrator has
 * to end up in the state history with that administrator.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnStateHandler::class)]
class OrderReturnStateHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function actionProvider(): iterable
    {
        yield 'approve' => [OrderReturnStates::ACTION_APPROVE];
        yield 'decline' => [OrderReturnStates::ACTION_DECLINE];
        yield 'cancel' => [OrderReturnStates::ACTION_CANCEL];
        yield 'ship' => [OrderReturnStates::ACTION_SHIP];
        yield 'receive' => [OrderReturnStates::ACTION_RECEIVE];
        yield 'refund' => [OrderReturnStates::ACTION_REFUND];
        yield 'finish' => [OrderReturnStates::ACTION_FINISH];
    }

    #[DataProvider('actionProvider')]
    public function testEveryActionTransitionsTheReturnItself(string $action): void
    {
        $returnId = 'return-id';
        $context = Context::createDefaultContext();
        $registry = $this->createMock(StateMachineRegistry::class);

        $registry->expects($this->once())
            ->method('transition')
            ->with(
                static::callback(static fn (Transition $transition): bool => $transition->getEntityName() === OrderReturnEntity::ENTITY_NAME
                    && $transition->getEntityId() === $returnId
                    && $transition->getTransitionName() === $action
                    && $transition->getStateFieldName() === 'stateId'),
                $context
            );

        $handler = new OrderReturnStateHandler($registry);

        match ($action) {
            OrderReturnStates::ACTION_APPROVE => $handler->approve($returnId, $context),
            OrderReturnStates::ACTION_DECLINE => $handler->decline($returnId, $context),
            OrderReturnStates::ACTION_CANCEL => $handler->cancel($returnId, $context),
            OrderReturnStates::ACTION_SHIP => $handler->ship($returnId, $context),
            OrderReturnStates::ACTION_RECEIVE => $handler->receive($returnId, $context),
            OrderReturnStates::ACTION_REFUND => $handler->refund($returnId, $context),
            OrderReturnStates::ACTION_FINISH => $handler->finish($returnId, $context),
            default => static::fail('The handler does not know the action ' . $action),
        };
    }

    public function testTheCurrentStateIsTheTechnicalName(): void
    {
        $state = (new StateMachineStateEntity())->assign(['technicalName' => OrderReturnStates::STATE_RETURNING]);
        $orderReturn = (new OrderReturnEntity())->assign(['stateMachineState' => $state]);

        $handler = new OrderReturnStateHandler(static::createStub(StateMachineRegistry::class));

        static::assertSame(OrderReturnStates::STATE_RETURNING, $handler->currentState($orderReturn));
    }

    public function testAReturnWithoutAStateHasNone(): void
    {
        $handler = new OrderReturnStateHandler(static::createStub(StateMachineRegistry::class));

        static::assertNull($handler->currentState(new OrderReturnEntity()));
    }
}
