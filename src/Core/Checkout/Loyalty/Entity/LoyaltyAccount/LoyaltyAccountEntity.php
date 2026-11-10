<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyAccountEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected ?CustomerEntity $customer = null;

    protected int $pointsBalance = 0;

    protected int $tierPoints = 0;

    protected ?string $tierId = null;

    protected ?LoyaltyTierEntity $tier = null;

    protected ?string $paidTierId = null;

    protected ?LoyaltyTierEntity $paidTier = null;

    protected ?\DateTimeInterface $membershipPaidUntil = null;

    protected ?LoyaltyPointTransactionCollection $transactions = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getPointsBalance(): int
    {
        return $this->pointsBalance;
    }

    public function setPointsBalance(int $pointsBalance): void
    {
        $this->pointsBalance = $pointsBalance;
    }

    public function getTierPoints(): int
    {
        return $this->tierPoints;
    }

    public function setTierPoints(int $tierPoints): void
    {
        $this->tierPoints = $tierPoints;
    }

    public function getTierId(): ?string
    {
        return $this->tierId;
    }

    public function setTierId(?string $tierId): void
    {
        $this->tierId = $tierId;
    }

    public function getTier(): ?LoyaltyTierEntity
    {
        return $this->tier;
    }

    public function setTier(LoyaltyTierEntity $tier): void
    {
        $this->tier = $tier;
    }

    public function getPaidTierId(): ?string
    {
        return $this->paidTierId;
    }

    public function setPaidTierId(?string $paidTierId): void
    {
        $this->paidTierId = $paidTierId;
    }

    public function getPaidTier(): ?LoyaltyTierEntity
    {
        return $this->paidTier;
    }

    public function setPaidTier(?LoyaltyTierEntity $paidTier): void
    {
        $this->paidTier = $paidTier;
    }

    public function getMembershipPaidUntil(): ?\DateTimeInterface
    {
        return $this->membershipPaidUntil;
    }

    public function setMembershipPaidUntil(\DateTimeInterface $membershipPaidUntil): void
    {
        $this->membershipPaidUntil = $membershipPaidUntil;
    }

    public function getTransactions(): ?LoyaltyPointTransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(LoyaltyPointTransactionCollection $transactions): void
    {
        $this->transactions = $transactions;
    }
}
