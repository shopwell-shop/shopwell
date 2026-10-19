<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentQuery::class)]
class PaymentQueryTest extends TestCase
{
    public function testItCarriesTheModelItWasBuiltWith(): void
    {
        $model = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
        ]);

        $query = new PaymentQuery($model);

        static::assertSame($model, $query->getModel());
    }

    public function testTheModelCanBeReplaced(): void
    {
        $query = new PaymentQuery(new PaymentGatewayModel());

        $replacement = new PaymentGatewayModel([
            PaymentGatewayModel::PAYMENT_NO => 'P-2',
        ]);
        $query->setModel($replacement);

        static::assertSame($replacement, $query->getModel());
    }

    public function testTheFirstModelCanBeReadBack(): void
    {
        $model = new PaymentGatewayModel();
        $query = new PaymentQuery($model);
        $query->setModel(new PaymentGatewayModel());

        static::assertSame($model, $query->getFirstModel());
    }
}
