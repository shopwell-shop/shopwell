<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\OrderReturn;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

/**
 * The three screens of "my returns" share one page type.
 *
 * They are the same page seen at a different depth: a list without a current return, a detail with
 * one, and a form with the positions that may still be returned. Splitting them into three classes
 * would triple the plumbing without changing what a template may read, so the parts that the current
 * screen does not need stay empty and the template asks for the part it renders.
 */
#[Package('after-sales')]
class AccountOrderReturnPage extends Page
{
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

    protected ?string $orderNumber = null;

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

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }
}
