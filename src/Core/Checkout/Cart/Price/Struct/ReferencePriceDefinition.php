<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\Framework\Util\FloatComparator;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ReferencePriceDefinition extends Struct
{
    public function __construct(
        protected float $purchaseUnit,
        protected float $referenceUnit,
        protected string $unitName
    ) {
        $this->purchaseUnit = FloatComparator::cast($purchaseUnit);
        $this->referenceUnit = FloatComparator::cast($referenceUnit);
    }

    public function getPurchaseUnit(): float
    {
        return FloatComparator::cast($this->purchaseUnit);
    }

    public function getReferenceUnit(): float
    {
        return FloatComparator::cast($this->referenceUnit);
    }

    public function getUnitName(): string
    {
        return $this->unitName;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_reference_definition';
    }
}
