<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Order\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Order\Transformer\TransactionTransformer;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TransactionTransformer::class)]
class TransactionTransformerTest extends TestCase
{
    public function testTransform(): void
    {
        $transaction = new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'test');
        $transaction->setValidationStruct(new ArrayStruct());

        $data = TransactionTransformer::transform($transaction, 'state', Context::createDefaultContext());

        static::assertSame('test', $data['paymentMethodId']);
        static::assertSame($transaction->getAmount(), $data['amount']);
        static::assertSame('state', $data['stateId']);
        static::assertSame($transaction->getValidationStruct()?->jsonSerialize(), $data['validationData']);
    }

    public function testTransformCollection(): void
    {
        $transaction = new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'test');
        $transaction->setValidationStruct(new ArrayStruct());

        $data = TransactionTransformer::transformCollection(new TransactionCollection([$transaction]), 'state', Context::createDefaultContext());
        $data = $data[0];

        static::assertSame('test', $data['paymentMethodId']);
        static::assertSame($transaction->getAmount(), $data['amount']);
        static::assertSame('state', $data['stateId']);
        static::assertSame($transaction->getValidationStruct()?->jsonSerialize(), $data['validationData']);
    }
}
