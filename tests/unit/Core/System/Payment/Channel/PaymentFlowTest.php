<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentFlow::class)]
class PaymentFlowTest extends TestCase
{
    /**
     * @return iterable<string, array{PaymentFlow, string}>
     */
    public static function flowProvider(): iterable
    {
        yield 'redirect' => [PaymentFlow::REDIRECT, 'redirect'];
        yield 'qrcode' => [PaymentFlow::QRCODE, 'qrcode'];
        yield 'sdk' => [PaymentFlow::SDK, 'sdk'];
    }

    #[DataProvider('flowProvider')]
    public function testTheBackedValueIsStable(PaymentFlow $flow, string $value): void
    {
        static::assertSame($value, $flow->value);
        static::assertSame($flow, PaymentFlow::from($value));
    }

    public function testThereAreOnlyTheThreeKnownFlows(): void
    {
        static::assertSame(['redirect', 'qrcode', 'sdk'], array_map(
            static fn (PaymentFlow $flow): string => $flow->value,
            PaymentFlow::cases()
        ));
    }

    public function testFromRejectsAnUnknownFlow(): void
    {
        static::assertNull(PaymentFlow::tryFrom('cash'));
    }
}
