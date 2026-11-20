<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalBindingEntity extends Entity
{
    use EntityIdTrait;

    protected string $businessType;

    protected string $businessVersion;

    protected int $revision;

    protected string $releaseId;

    protected string $formSchemaId;

    protected string $formSchemaHash;

    protected string $formSchema;

    protected ?string $createdById = null;

    public function getBusinessType(): string
    {
        return $this->businessType;
    }

    public function setBusinessType(string $businessType): void
    {
        $this->businessType = $businessType;
    }

    public function getBusinessVersion(): string
    {
        return $this->businessVersion;
    }

    public function setBusinessVersion(string $businessVersion): void
    {
        $this->businessVersion = $businessVersion;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): void
    {
        $this->revision = $revision;
    }

    public function getReleaseId(): string
    {
        return $this->releaseId;
    }

    public function setReleaseId(string $releaseId): void
    {
        $this->releaseId = $releaseId;
    }

    public function getFormSchemaId(): string
    {
        return $this->formSchemaId;
    }

    public function setFormSchemaId(string $formSchemaId): void
    {
        $this->formSchemaId = $formSchemaId;
    }

    public function getFormSchemaHash(): string
    {
        return $this->formSchemaHash;
    }

    public function setFormSchemaHash(string $formSchemaHash): void
    {
        $this->formSchemaHash = $formSchemaHash;
    }

    public function getFormSchema(): string
    {
        return $this->formSchema;
    }

    public function setFormSchema(string $formSchema): void
    {
        $this->formSchema = $formSchema;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(?string $createdById): void
    {
        $this->createdById = $createdById;
    }
}
