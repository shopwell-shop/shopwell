<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerLoyaltyEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected ?CustomerEntity $customer = null;

    protected int $pointsBalance = 0;

    protected int $growthValue = 0;

    protected ?string $membershipTierId = null;

    protected ?LoyaltyTierEntity $membershipTier = null;

    protected ?\DateTimeInterface $membershipValidUntil = null;

    protected ?CustomerPointTransactionCollection $transactions = null;

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

    public function getGrowthValue(): int
    {
        return $this->growthValue;
    }

    public function setGrowthValue(int $growthValue): void
    {
        $this->growthValue = $growthValue;
    }

    public function getMembershipTierId(): ?string
    {
        return $this->membershipTierId;
    }

    public function setMembershipTierId(?string $membershipTierId): void
    {
        $this->membershipTierId = $membershipTierId;
    }

    public function getMembershipTier(): ?LoyaltyTierEntity
    {
        return $this->membershipTier;
    }

    public function setMembershipTier(LoyaltyTierEntity $membershipTier): void
    {
        $this->membershipTier = $membershipTier;
    }

    public function getMembershipValidUntil(): ?\DateTimeInterface
    {
        return $this->membershipValidUntil;
    }

    public function setMembershipValidUntil(\DateTimeInterface $membershipValidUntil): void
    {
        $this->membershipValidUntil = $membershipValidUntil;
    }

    public function getTransactions(): ?CustomerPointTransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(CustomerPointTransactionCollection $transactions): void
    {
        $this->transactions = $transactions;
    }
}
