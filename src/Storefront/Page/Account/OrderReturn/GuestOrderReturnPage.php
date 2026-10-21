<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\OrderReturn;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

/**
 * What a guest sees of their returns.
 *
 * A guest has no account, so this page starts as a form (order number and mail address) and only
 * becomes a list once the store-api verified the pair and handed back a token. `verified` is what the
 * template switches on: showing a list that was never authorised would be a lie, and showing the form
 * again would hide that the token that was carried expired.
 */
#[Package('after-sales')]
class GuestOrderReturnPage extends Page
{
    protected bool $verified = false;

    protected ?string $orderNumber = null;

    /**
     * @var list<array<string, mixed>>
     */
    protected array $returns = [];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $current = null;

    /**
     * @var list<array<string, mixed>>
     */
    protected array $positions = [];

    /**
     * @var list<array<string, mixed>>
     */
    protected array $reasons = [];

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): void
    {
        $this->verified = $verified;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getReturns(): array
    {
        return $this->returns;
    }

    /**
     * @param list<array<string, mixed>> $returns
     */
    public function setReturns(array $returns): void
    {
        $this->returns = $returns;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCurrent(): ?array
    {
        return $this->current;
    }

    /**
     * @param array<string, mixed>|null $current
     */
    public function setCurrent(?array $current): void
    {
        $this->current = $current;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPositions(): array
    {
        return $this->positions;
    }

    /**
     * @param list<array<string, mixed>> $positions
     */
    public function setPositions(array $positions): void
    {
        $this->positions = $positions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    /**
     * @param list<array<string, mixed>> $reasons
     */
    public function setReasons(array $reasons): void
    {
        $this->reasons = $reasons;
    }
}
