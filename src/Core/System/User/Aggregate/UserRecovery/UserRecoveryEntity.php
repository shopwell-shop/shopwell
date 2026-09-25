<?php declare(strict_types=1);

namespace Shopwell\Core\System\User\Aggregate\UserRecovery;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserEntity;

#[Package('fundamentals@framework')]
class UserRecoveryEntity extends Entity
{
    use EntityIdTrait;

    protected string $userId;

    protected string $hash;

    protected ?UserEntity $user = null;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getHash(): string
    {
        $this->checkIfPropertyAccessIsAllowed('hash');

        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }
}
