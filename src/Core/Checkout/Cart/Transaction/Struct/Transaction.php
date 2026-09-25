<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Transaction\Struct;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Transaction extends Struct
{
    protected ?Struct $validationStruct = null;

    public function __construct(
        protected CalculatedPrice $amount,
        protected string $paymentMethodId
    ) {
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getValidationStruct(): ?Struct
    {
        return $this->validationStruct;
    }

    public function setValidationStruct(?Struct $validationStruct): void
    {
        $this->validationStruct = $validationStruct;
    }

    public function getApiAlias(): string
    {
        return 'cart_transaction';
    }
}
