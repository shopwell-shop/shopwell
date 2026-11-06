<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use Shopwell\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.9.0 - Will be removed
 */
#[Package('after-sales')]
class DocumentTypeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $documentTypeId;

    protected ?DocumentTypeEntity $documentType = null;

    protected ?string $name = null;

    public function __construct()
    {
        Feature::throwIfActive('v6.9.0.0', Feature::deprecatedClassMessage(self::class, 'v6.9.0.0'));
    }

    public function getDocumentTypeId(): string
    {
        return $this->documentTypeId;
    }

    public function setDocumentTypeId(string $documentTypeId): void
    {
        $this->documentTypeId = $documentTypeId;
    }

    public function getDocumentType(): ?DocumentTypeEntity
    {
        return $this->documentType;
    }

    public function setDocumentType(DocumentTypeEntity $documentType): void
    {
        $this->documentType = $documentType;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
