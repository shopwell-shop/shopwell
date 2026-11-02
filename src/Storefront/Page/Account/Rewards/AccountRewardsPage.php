<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Rewards;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Storefront\Page\Page;

/**
 * What the customer earned: the balance, its worth, the tier it put them in and the ladder they climb.
 *
 * A paid membership has its own page and its own page object - this one never carries an offer, so a
 * template that renders the rewards page cannot accidentally render a price.
 */
#[Package('checkout')]
class AccountRewardsPage extends Page
{
    protected ArrayStruct $rewards;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $currentTier = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $nextTier = null;

    public function getRewards(): ArrayStruct
    {
        return $this->rewards;
    }

    public function setRewards(ArrayStruct $rewards): void
    {
        $this->rewards = $rewards;
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
