<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Notification;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Integration\IntegrationEntity;
use Shopwell\Core\System\User\UserEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[ClassMoved(version: 'v6.8.0', previousClassName: 'Shopwell\Administration\Notification\NotificationEntity')]
class NotificationEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $createdByIntegrationId = null;

    protected ?IntegrationEntity $createdByIntegration = null;

    protected ?string $createdByUserId = null;

    protected ?UserEntity $createdByUser = null;

    protected bool $adminOnly;

    /**
     * @var array<string>
     */
    protected array $requiredPrivileges = [];

    protected string $status;

    protected string $message;

    public function getCreatedByIntegrationId(): ?string
    {
        return $this->createdByIntegrationId;
    }

    public function setCreatedByIntegrationId(string $createdByIntegrationId): void
    {
        $this->createdByIntegrationId = $createdByIntegrationId;
    }

    public function getCreatedByIntegration(): ?IntegrationEntity
    {
        return $this->createdByIntegration;
    }

    public function setCreatedByIntegration(IntegrationEntity $createdByIntegration): void
    {
        $this->createdByIntegration = $createdByIntegration;
    }

    public function getCreatedByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(string $createdByUserId): void
    {
        $this->createdByUserId = $createdByUserId;
    }

    public function getCreatedByUser(): ?UserEntity
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(UserEntity $createdByUser): void
    {
        $this->createdByUser = $createdByUser;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }

    public function setAdminOnly(bool $adminOnly): void
    {
        $this->adminOnly = $adminOnly;
    }

    /**
     * @return array<string>
     */
    public function getRequiredPrivileges(): array
    {
        return $this->requiredPrivileges;
    }

    /**
     * @param array<string> $requiredPrivileges
     */
    public function setRequiredPrivileges(array $requiredPrivileges): void
    {
        $this->requiredPrivileges = $requiredPrivileges;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
