<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Transaction\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Transaction::class)]
class TransactionTest extends TestCase
{
    public function testProtectedPayloadValuesStayOutOfTheSerializedStruct(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setPayload(['installments' => 3, 'psp' => 'ref-1'], ['psp' => true]);

        static::assertSame(['installments' => 3], $transaction->jsonSerialize()['payload']);
    }

    private function createTransaction(): Transaction
    {
        return new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'payment-method-id');
    }
}
