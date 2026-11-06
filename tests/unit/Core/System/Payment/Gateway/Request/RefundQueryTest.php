<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RefundQuery::class)]
class RefundQueryTest extends TestCase
{
    public function testQueriesUseTheOriginalModelAndKnownRemoteReference(): void
    {
        $model = new PaymentGatewayModel();
        $query = new RefundQuery($model, 'remote-refund');

        static::assertSame($model, $query->getModel());
        static::assertSame('remote-refund', $query->outRefundId);
        static::assertNull((new RefundQuery($model))->outRefundId);
    }
}
