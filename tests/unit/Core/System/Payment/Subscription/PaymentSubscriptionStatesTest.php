<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentSubscriptionStates::class)]
class PaymentSubscriptionStatesTest extends TestCase
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
        yield 'state machine' => ['payment_subscription.status', PaymentSubscriptionStates::STATE_MACHINE];
        yield 'state pending' => ['pending', PaymentSubscriptionStates::STATE_PENDING];
        yield 'state trialing' => ['trialing', PaymentSubscriptionStates::STATE_TRIALING];
        yield 'state active' => ['active', PaymentSubscriptionStates::STATE_ACTIVE];
        yield 'state past due' => ['past_due', PaymentSubscriptionStates::STATE_PAST_DUE];
        yield 'state suspended' => ['suspended', PaymentSubscriptionStates::STATE_SUSPENDED];
        yield 'state canceled' => ['canceled', PaymentSubscriptionStates::STATE_CANCELED];
        yield 'state expired' => ['expired', PaymentSubscriptionStates::STATE_EXPIRED];
        yield 'action trial' => ['trial', PaymentSubscriptionStates::ACTION_TRIAL];
        yield 'action activate' => ['activate', PaymentSubscriptionStates::ACTION_ACTIVATE];
        yield 'action fail' => ['fail', PaymentSubscriptionStates::ACTION_FAIL];
        yield 'action suspend' => ['suspend', PaymentSubscriptionStates::ACTION_SUSPEND];
        yield 'action cancel' => ['cancel', PaymentSubscriptionStates::ACTION_CANCEL];
        yield 'action expire' => ['expire', PaymentSubscriptionStates::ACTION_EXPIRE];
    }

    public function testEveryStateNameIsUsedOnlyOnce(): void
    {
        $states = [
            PaymentSubscriptionStates::STATE_PENDING,
            PaymentSubscriptionStates::STATE_TRIALING,
            PaymentSubscriptionStates::STATE_ACTIVE,
            PaymentSubscriptionStates::STATE_PAST_DUE,
            PaymentSubscriptionStates::STATE_SUSPENDED,
            PaymentSubscriptionStates::STATE_CANCELED,
            PaymentSubscriptionStates::STATE_EXPIRED,
        ];

        static::assertSame($states, array_values(array_unique($states)));
    }
}
