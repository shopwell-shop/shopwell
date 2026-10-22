<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserPosition;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Position\PositionEntity;
use Shopwell\Core\System\User\UserEntity;

/**
 * A single assignment of a position to a user. The mapping table has no id of
 * its own - the pair of user and position is the identity.
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserPositionEntity extends Entity
{
    protected string $userId;

    protected string $positionId;

    protected ?UserEntity $user = null;

    protected ?PositionEntity $position = null;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getPositionId(): string
    {
        return $this->positionId;
    }

    public function setPositionId(string $positionId): void
    {
        $this->positionId = $positionId;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getPosition(): ?PositionEntity
    {
        return $this->position;
    }

    public function setPosition(?PositionEntity $position): void
    {
        $this->position = $position;
    }
}
