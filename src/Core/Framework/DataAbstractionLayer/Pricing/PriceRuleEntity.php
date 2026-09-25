<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Pricing;

use Shopwell\Core\Framework\DataAbstractionLayer\Contract\IdAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PriceRuleEntity extends Entity implements IdAware
{
    use EntityIdTrait;

    protected string $ruleId;

    protected PriceCollection $price;

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function setRuleId(string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getPrice(): PriceCollection
    {
        return $this->price;
    }

    public function setPrice(PriceCollection $price): void
    {
        $this->price = $price;
    }
}
