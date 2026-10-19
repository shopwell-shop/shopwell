<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderStates::class)]
class PaymentOrderStatesTest extends TestCase
{
    #[DataProvider('stateMachines')]
    public function testTheStateMachineNameIsStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function stateMachines(): \Generator
    {
        yield 'payment order' => ['payment_order.state', PaymentOrderStates::STATE_MACHINE];
    }

    #[DataProvider('stateNames')]
    public function testTheStateNamesAreStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function stateNames(): \Generator
    {
        yield 'created' => ['created', PaymentOrderStates::STATE_CREATED];
        yield 'opened' => ['opened', PaymentOrderStates::STATE_OPENED];
        yield 'paid' => ['paid', PaymentOrderStates::STATE_PAID];
        yield 'failed' => ['failed', PaymentOrderStates::STATE_FAILED];
        yield 'closed' => ['closed', PaymentOrderStates::STATE_CLOSED];
        yield 'expired' => ['expired', PaymentOrderStates::STATE_EXPIRED];
        yield 'refunded partially' => ['refunded_partially', PaymentOrderStates::STATE_REFUNDED_PARTIALLY];
        yield 'refunded' => ['refunded', PaymentOrderStates::STATE_REFUNDED];
    }

    #[DataProvider('actionNames')]
    public function testTheActionNamesAreStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function actionNames(): \Generator
    {
        yield 'process' => ['process', PaymentOrderStates::ACTION_PROCESS];
        yield 'pay' => ['pay', PaymentOrderStates::ACTION_PAY];
        yield 'fail' => ['fail', PaymentOrderStates::ACTION_FAIL];
        yield 'close' => ['close', PaymentOrderStates::ACTION_CLOSE];
        yield 'expire' => ['expire', PaymentOrderStates::ACTION_EXPIRE];
        yield 'refund' => ['refund', PaymentOrderStates::ACTION_REFUND];
        yield 'refund partially' => ['refund_partially', PaymentOrderStates::ACTION_REFUND_PARTIALLY];
    }

    public function testEveryStateNameIsUsedOnlyOnce(): void
    {
        $states = [
            PaymentOrderStates::STATE_CREATED,
            PaymentOrderStates::STATE_OPENED,
            PaymentOrderStates::STATE_PAID,
            PaymentOrderStates::STATE_FAILED,
            PaymentOrderStates::STATE_CLOSED,
            PaymentOrderStates::STATE_EXPIRED,
            PaymentOrderStates::STATE_REFUNDED_PARTIALLY,
            PaymentOrderStates::STATE_REFUNDED,
        ];

        static::assertSame($states, array_values(array_unique($states)));
    }
}
