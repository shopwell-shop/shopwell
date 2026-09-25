<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Tax;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class PercentageTaxRuleBuilder
{
    public function buildRules(CalculatedPrice $price): TaxRuleCollection
    {
        return $this->buildCollectionRules($price->getCalculatedTaxes(), $price->getTotalPrice());
    }

    public function buildCollectionRules(CalculatedTaxCollection $taxes, float $totalPrice): TaxRuleCollection
    {
        $rules = new TaxRuleCollection([]);

        if ($taxes->count() === 0) {
            return $rules;
        }

        $equalShare = $totalPrice !== 0.0 ? null : 100.0 / $taxes->count();

        foreach ($taxes as $tax) {
            $percentage = $equalShare ?? ($tax->getPrice() / $totalPrice * 100);
            $rules->add(new TaxRule($tax->getTaxRate(), $percentage));
        }

        return $rules;
    }
}
