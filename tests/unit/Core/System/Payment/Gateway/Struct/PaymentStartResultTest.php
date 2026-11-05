<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentStartResult::class)]
class PaymentStartResultTest extends TestCase
{
    public function testKeepsTheParametersAndDefaultsToNoChannelReference(): void
    {
        $params = new PaymentHandover(PaymentInteraction::REDIRECT, 'https://example.com/pay');

        $result = new PaymentStartResult($params);

        static::assertSame($params, $result->params);
        static::assertNull($result->outPaymentNo);
    }

    public function testKeepsTheChannelReference(): void
    {
        $params = new PaymentHandover(PaymentInteraction::QRCODE);

        $result = new PaymentStartResult($params, 'trade-no-123');

        static::assertSame($params, $result->params);
        static::assertSame('trade-no-123', $result->outPaymentNo);
    }
}
