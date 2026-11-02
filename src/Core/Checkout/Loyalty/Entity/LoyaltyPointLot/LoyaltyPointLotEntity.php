<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyPointLotEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected int $points;

    protected int $remainingPoints;

    protected ?\DateTimeInterface $expiresAt = null;

    protected ?\DateTimeInterface $expiredAt = null;

    protected ?string $orderId = null;

    protected ?string $orderVersionId = null;

    protected ?CustomerEntity $customer = null;

    protected ?OrderEntity $order = null;

    protected ?LoyaltyPointLotUseCollection $uses = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getRemainingPoints(): int
    {
        return $this->remainingPoints;
    }

    public function setRemainingPoints(int $remainingPoints): void
    {
        $this->remainingPoints = $remainingPoints;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getExpiredAt(): ?\DateTimeInterface
    {
        return $this->expiredAt;
    }

    public function setExpiredAt(?\DateTimeInterface $expiredAt): void
    {
        $this->expiredAt = $expiredAt;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): ?string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(?string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getUses(): ?LoyaltyPointLotUseCollection
    {
        return $this->uses;
    }

    public function setUses(LoyaltyPointLotUseCollection $uses): void
    {
        $this->uses = $uses;
    }
}
