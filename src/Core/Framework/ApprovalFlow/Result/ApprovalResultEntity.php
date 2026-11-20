<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalResultEntity extends Entity
{
    use EntityIdTrait;

    protected string $instanceId;

    protected string $releaseId;

    protected string $commandId;

    protected int $aggregateVersion;

    protected ApprovalOutcome $outcome;

    protected \DateTimeImmutable $finishedAt;

    protected ?ApprovalInstanceEntity $instance = null;

    protected ?ApprovalReleaseEntity $release = null;

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function setInstanceId(string $instanceId): void
    {
        $this->instanceId = $instanceId;
    }

    public function getReleaseId(): string
    {
        return $this->releaseId;
    }

    public function setReleaseId(string $releaseId): void
    {
        $this->releaseId = $releaseId;
    }

    public function getCommandId(): string
    {
        return $this->commandId;
    }

    public function setCommandId(string $commandId): void
    {
        $this->commandId = $commandId;
    }

    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function setAggregateVersion(int $aggregateVersion): void
    {
        $this->aggregateVersion = $aggregateVersion;
    }

    public function getOutcome(): ApprovalOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(ApprovalOutcome $outcome): void
    {
        $this->outcome = $outcome;
    }

    public function getFinishedAt(): \DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(\DateTimeImmutable $finishedAt): void
    {
        $this->finishedAt = $finishedAt;
    }

    public function getInstance(): ?ApprovalInstanceEntity
    {
        return $this->instance;
    }

    public function setInstance(?ApprovalInstanceEntity $instance): void
    {
        $this->instance = $instance;
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
