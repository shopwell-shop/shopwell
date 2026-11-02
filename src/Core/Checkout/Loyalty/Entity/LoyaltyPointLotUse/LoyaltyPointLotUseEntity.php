<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse;

use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyPointLotUseEntity extends Entity
{
    use EntityIdTrait;

    protected string $lotId;

    protected string $transactionId;

    protected int $points;

    protected int $returnedPoints;

    protected ?LoyaltyPointLotEntity $lot = null;

    protected ?CustomerPointTransactionEntity $transaction = null;

    public function getLotId(): string
    {
        return $this->lotId;
    }

    public function setLotId(string $lotId): void
    {
        $this->lotId = $lotId;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getReturnedPoints(): int
    {
        return $this->returnedPoints;
    }

    public function setReturnedPoints(int $returnedPoints): void
    {
        $this->returnedPoints = $returnedPoints;
    }

    public function getLot(): ?LoyaltyPointLotEntity
    {
        return $this->lot;
    }

    public function setLot(LoyaltyPointLotEntity $lot): void
    {
        $this->lot = $lot;
    }

    public function getTransaction(): ?CustomerPointTransactionEntity
    {
        return $this->transaction;
    }

    public function setTransaction(CustomerPointTransactionEntity $transaction): void
    {
        $this->transaction = $transaction;
    }
}
