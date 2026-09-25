<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Struct;

use Shopwell\Core\Checkout\Document\Service\PdfRenderer;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: DocumentGenerationRequest::class,
)]
final class DocumentGenerateOperation extends Struct
{
    protected ?string $documentId = null;

    protected string $orderVersionId = Defaults::LIVE_VERSION;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected string $orderId,
        protected string $fileType = PdfRenderer::FILE_EXTENSION,
        protected array $config = [],
        protected ?string $referencedDocumentId = null,
        protected bool $static = false,
        protected bool $preview = false,
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function setReferencedDocumentId(string $referencedDocumentId): void
    {
        $this->referencedDocumentId = $referencedDocumentId;
    }

    public function getReferencedDocumentId(): ?string
    {
        return $this->referencedDocumentId;
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    public function getDocumentId(): ?string
    {
        return $this->documentId;
    }

    public function setDocumentId(string $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }
}
