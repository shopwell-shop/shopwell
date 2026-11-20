<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Request;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalRequestEntity extends Entity
{
    use EntityIdTrait;

    protected string $businessType;

    protected string $businessId;

    protected int $submissionRound;

    protected string $businessRevision;

    protected string $bindingId;

    protected string $instanceId;

    protected ?ApprovalInstanceEntity $instance = null;

    protected string $initiatorId;

    protected string $formJson;

    public function getBusinessType(): string
    {
        return $this->businessType;
    }

    public function setBusinessType(string $businessType): void
    {
        $this->businessType = $businessType;
    }

    public function getBusinessId(): string
    {
        return $this->businessId;
    }

    public function setBusinessId(string $businessId): void
    {
        $this->businessId = $businessId;
    }

    public function getSubmissionRound(): int
    {
        return $this->submissionRound;
    }

    public function setSubmissionRound(int $submissionRound): void
    {
        $this->submissionRound = $submissionRound;
    }

    public function getBusinessRevision(): string
    {
        return $this->businessRevision;
    }

    public function setBusinessRevision(string $businessRevision): void
    {
        $this->businessRevision = $businessRevision;
    }

    public function getBindingId(): string
    {
        return $this->bindingId;
    }

    public function setBindingId(string $bindingId): void
    {
        $this->bindingId = $bindingId;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function setInstanceId(string $instanceId): void
    {
        $this->instanceId = $instanceId;
    }

    public function getInstance(): ?ApprovalInstanceEntity
    {
        return $this->instance;
    }

    public function setInstance(?ApprovalInstanceEntity $instance): void
    {
        $this->instance = $instance;
    }

    public function getInitiatorId(): string
    {
        return $this->initiatorId;
    }

    public function setInitiatorId(string $initiatorId): void
    {
        $this->initiatorId = $initiatorId;
    }

    public function getFormJson(): string
    {
        return $this->formJson;
    }

    public function setFormJson(string $formJson): void
    {
        $this->formJson = $formJson;
    }
}
