<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalInstanceEntity extends Entity
{
    use EntityIdTrait;

    protected string $releaseId;

    protected ApprovalInstanceStatus $status;

    protected int $aggregateVersion;

    protected ?ApprovalReleaseEntity $release = null;

    public function getReleaseId(): string
    {
        return $this->releaseId;
    }

    public function setReleaseId(string $releaseId): void
    {
        $this->releaseId = $releaseId;
    }

    public function getStatus(): ApprovalInstanceStatus
    {
        return $this->status;
    }

    public function setStatus(ApprovalInstanceStatus $status): void
    {
        $this->status = $status;
    }

    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function setAggregateVersion(int $aggregateVersion): void
    {
        $this->aggregateVersion = $aggregateVersion;
    }

    public function getRelease(): ?ApprovalReleaseEntity
    {
        return $this->release;
    }

    public function setRelease(?ApprovalReleaseEntity $release): void
    {
        $this->release = $release;
    }
}
