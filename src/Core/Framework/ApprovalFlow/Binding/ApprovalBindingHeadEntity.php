<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalBindingHeadEntity extends Entity
{
    use EntityIdTrait;

    protected string $businessType;

    protected int $latestRevision;

    protected ?int $activeRevision = null;

    protected int $version;

    public function getBusinessType(): string
    {
        return $this->businessType;
    }

    public function setBusinessType(string $businessType): void
    {
        $this->businessType = $businessType;
    }

    public function getLatestRevision(): int
    {
        return $this->latestRevision;
    }

    public function setLatestRevision(int $latestRevision): void
    {
        $this->latestRevision = $latestRevision;
    }

    public function getActiveRevision(): ?int
    {
        return $this->activeRevision;
    }

    public function setActiveRevision(?int $activeRevision): void
    {
        $this->activeRevision = $activeRevision;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }
}
