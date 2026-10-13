<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentRefundStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundStates::class)]
class PaymentRefundStatesTest extends TestCase
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
        yield 'payment refund' => ['payment_refund.state', PaymentRefundStates::STATE_MACHINE];
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
        yield 'open' => ['open', PaymentRefundStates::STATE_OPEN];
        yield 'processing' => ['processing', PaymentRefundStates::STATE_PROCESSING];
        yield 'finished' => ['finished', PaymentRefundStates::STATE_FINISHED];
        yield 'failed' => ['failed', PaymentRefundStates::STATE_FAILED];
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
        yield 'process' => ['process', PaymentRefundStates::ACTION_PROCESS];
        yield 'finish' => ['finish', PaymentRefundStates::ACTION_FINISH];
        yield 'fail' => ['fail', PaymentRefundStates::ACTION_FAIL];
    }

    public function testEveryStateNameIsUsedOnlyOnce(): void
    {
        $states = [
            PaymentRefundStates::STATE_OPEN,
            PaymentRefundStates::STATE_PROCESSING,
            PaymentRefundStates::STATE_FINISHED,
            PaymentRefundStates::STATE_FAILED,
        ];

        static::assertSame($states, array_values(array_unique($states)));
    }
}
