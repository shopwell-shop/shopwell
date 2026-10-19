<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Loyalty;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class AccountLoyaltyPage extends Page
{
    protected ArrayStruct $loyalty;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $currentTier = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $nextTier = null;

    public function getLoyalty(): ArrayStruct
    {
        return $this->loyalty;
    }

    public function setLoyalty(ArrayStruct $loyalty): void
    {
        $this->loyalty = $loyalty;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCurrentTier(): ?array
    {
        return $this->currentTier;
    }

    /**
     * @param array<string, mixed>|null $currentTier
     */
    public function setCurrentTier(?array $currentTier): void
    {
        $this->currentTier = $currentTier;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNextTier(): ?array
    {
        return $this->nextTier;
    }

    /**
     * @param array<string, mixed>|null $nextTier
     */
    public function setNextTier(?array $nextTier): void
    {
        $this->nextTier = $nextTier;
    }
}
