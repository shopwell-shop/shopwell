<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PayResult::class)]
class PayResultTest extends TestCase
{
    public function testKeepsTheParametersAndDefaultsToNoChannelReference(): void
    {
        $params = new PayParams(PaymentFlow::REDIRECT, 'https://example.com/pay');

        $result = new PayResult($params);

        static::assertSame($params, $result->params);
        static::assertNull($result->outPaymentNo);
    }

    public function testKeepsTheChannelReference(): void
    {
        $params = new PayParams(PaymentFlow::QRCODE);

        $result = new PayResult($params, 'trade-no-123');

        static::assertSame($params, $result->params);
        static::assertSame('trade-no-123', $result->outPaymentNo);
    }
}
