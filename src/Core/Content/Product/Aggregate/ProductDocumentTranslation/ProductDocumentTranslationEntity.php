<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductDocumentTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $productDocumentId;

    protected string $productDocumentVersionId;

    protected ?string $title = null;

    protected ?string $mediaId = null;

    protected ?ProductDocumentEntity $productDocument = null;

    protected ?MediaEntity $media = null;

    public function getProductDocumentId(): string
    {
        return $this->productDocumentId;
    }

    public function setProductDocumentId(string $productDocumentId): void
    {
        $this->productDocumentId = $productDocumentId;
    }

    public function getProductDocumentVersionId(): string
    {
        return $this->productDocumentVersionId;
    }

    public function setProductDocumentVersionId(string $productDocumentVersionId): void
    {
        $this->productDocumentVersionId = $productDocumentVersionId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getProductDocument(): ?ProductDocumentEntity
    {
        return $this->productDocument;
    }

    public function setProductDocument(ProductDocumentEntity $productDocument): void
    {
        $this->productDocument = $productDocument;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }
}
