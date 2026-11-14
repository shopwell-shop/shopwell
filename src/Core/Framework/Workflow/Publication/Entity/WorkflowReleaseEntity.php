<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Entity;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * Published definition snapshot passed to business publication targets.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WorkflowReleaseEntity extends Entity
{
    use EntityIdTrait;

    protected string $draftId;

    protected int $revision;

    protected string $name;

    protected string $publisher;

    protected string $document;

    protected string $publishedBy;

    public function getDraftId(): string
    {
        return $this->draftId;
    }

    public function setDraftId(string $draftId): void
    {
        $this->draftId = $draftId;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): void
    {
        $this->revision = $revision;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    public function setPublisher(string $publisher): void
    {
        $this->publisher = $publisher;
    }

    public function getDocument(): string
    {
        return $this->document;
    }

    public function setDocument(string $document): void
    {
        $this->document = $document;
    }

    public function getPublishedBy(): string
    {
        return $this->publishedBy;
    }

    public function setPublishedBy(string $publishedBy): void
    {
        $this->publishedBy = $publishedBy;
    }
}
