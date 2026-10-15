<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerPointTransactionEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected string $type;

    protected int $points;

    protected int $balanceAfter;

    protected ?string $idempotencyKey = null;

    protected ?string $orderId = null;

    protected ?string $salesChannelId = null;

    protected ?\DateTimeInterface $expiresAt = null;

    protected ?\DateTimeInterface $expiredAt = null;

    protected ?string $comment = null;

    protected ?string $administratorId = null;

    protected ?CustomerEntity $customer = null;

    protected ?OrderEntity $order = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(int $balanceAfter): void
    {
        $this->balanceAfter = $balanceAfter;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getAdministratorId(): ?string
    {
        return $this->administratorId;
    }

    public function setAdministratorId(?string $administratorId): void
    {
        $this->administratorId = $administratorId;
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

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
