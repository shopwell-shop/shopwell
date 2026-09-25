<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Payment\Payload\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopwell\Core\Framework\App\Payload\Source;
use Shopwell\Core\Framework\App\Payment\Payload\Struct\PaymentPayload;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentPayload::class)]
class PaymentPayloadTest extends TestCase
{
    public function testPayload(): void
    {
        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();
        $returnUrl = 'https://foo.bar';
        $requestData = ['foo' => 'bar'];
        $validateStruct = new ArrayStruct();
        $recurring = new RecurringDataStruct('foo', new \DateTime());
        $source = new Source('foo', 'bar', '1.0.0');

        $payload = new PaymentPayload($transaction, $order, $requestData, $returnUrl, $validateStruct, $recurring);
        $payload->setSource($source);

        static::assertEquals($transaction, $payload->getOrderTransaction());
        static::assertSame($order, $payload->getOrder());
        static::assertSame($returnUrl, $payload->getReturnUrl());
        static::assertSame($requestData, $payload->getRequestData());
        static::assertSame($validateStruct, $payload->getValidateStruct());
        static::assertSame($recurring, $payload->getRecurring());
        static::assertSame($source, $payload->getSource());
    }
}
