<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Framework\Log\Package;

/**
 * The two lists are what the quantity guard counts on: a state may either hold a claim on the goods
 * or not, never both, and every state has to be sorted into one of them.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnStates::class)]
class OrderReturnStatesTest extends TestCase
{
    private const ALL_STATES = [
        OrderReturnStates::STATE_REQUESTED,
        OrderReturnStates::STATE_OPEN,
        OrderReturnStates::STATE_RETURNING,
        OrderReturnStates::STATE_RECEIVED,
        OrderReturnStates::STATE_REFUNDING,
        OrderReturnStates::STATE_CLOSED,
        OrderReturnStates::STATE_DECLINED,
        OrderReturnStates::STATE_CANCELED,
    ];

    public function testAReturnThatTookTheGoodsBackStillBlocks(): void
    {
        static::assertContains(OrderReturnStates::STATE_CLOSED, OrderReturnStates::blockingStates());
    }

    public function testADeclinedOrWithdrawnReturnFreesItsQuantityAgain(): void
    {
        $blocking = OrderReturnStates::blockingStates();

        static::assertNotContains(OrderReturnStates::STATE_DECLINED, $blocking);
        static::assertNotContains(OrderReturnStates::STATE_CANCELED, $blocking);
    }

    public function testEveryStateIsSortedIntoOneOfTheLists(): void
    {
        $sorted = [...OrderReturnStates::finalStates(), ...OrderReturnStates::blockingStates()];

        foreach (self::ALL_STATES as $state) {
            static::assertContains($state, $sorted, 'the state "' . $state . '" is neither final nor blocking');
        }
    }

    public function testTheActionsAreNoStates(): void
    {
        $actions = [
            OrderReturnStates::ACTION_APPROVE,
            OrderReturnStates::ACTION_DECLINE,
            OrderReturnStates::ACTION_CANCEL,
            OrderReturnStates::ACTION_SHIP,
            OrderReturnStates::ACTION_RECEIVE,
            OrderReturnStates::ACTION_REFUND,
            OrderReturnStates::ACTION_FINISH,
        ];

        static::assertSame([], array_intersect($actions, self::ALL_STATES));
    }
}
