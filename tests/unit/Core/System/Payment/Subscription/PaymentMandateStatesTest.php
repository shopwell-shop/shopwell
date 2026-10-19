<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentMandateStates::class)]
class PaymentMandateStatesTest extends TestCase
{
    #[DataProvider('names')]
    public function testTheNamesAreStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function names(): \Generator
    {
        yield 'state machine' => ['payment_mandate.status', PaymentMandateStates::STATE_MACHINE];
        yield 'state init' => ['init', PaymentMandateStates::STATE_INIT];
        yield 'state pending' => ['pending', PaymentMandateStates::STATE_PENDING];
        yield 'state active' => ['active', PaymentMandateStates::STATE_ACTIVE];
        yield 'state suspended' => ['suspended', PaymentMandateStates::STATE_SUSPENDED];
        yield 'state terminated' => ['terminated', PaymentMandateStates::STATE_TERMINATED];
        yield 'state expired' => ['expired', PaymentMandateStates::STATE_EXPIRED];
        yield 'action submit' => ['submit', PaymentMandateStates::ACTION_SUBMIT];
        yield 'action activate' => ['activate', PaymentMandateStates::ACTION_ACTIVATE];
        yield 'action resume' => ['resume', PaymentMandateStates::ACTION_RESUME];
        yield 'action suspend' => ['suspend', PaymentMandateStates::ACTION_SUSPEND];
        yield 'action terminate' => ['terminate', PaymentMandateStates::ACTION_TERMINATE];
        yield 'action expire' => ['expire', PaymentMandateStates::ACTION_EXPIRE];
    }

    public function testEveryStateNameIsUsedOnlyOnce(): void
    {
        $states = [
            PaymentMandateStates::STATE_INIT,
            PaymentMandateStates::STATE_PENDING,
            PaymentMandateStates::STATE_ACTIVE,
            PaymentMandateStates::STATE_SUSPENDED,
            PaymentMandateStates::STATE_TERMINATED,
            PaymentMandateStates::STATE_EXPIRED,
        ];

        static::assertSame($states, array_values(array_unique($states)));
    }
}
