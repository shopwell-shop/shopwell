<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Event;

use Shopwell\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopwell\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Event\GenericEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: AbstractDocumentDataProvider::class,
    description: 'Enrich the order criteria via enrichOrderCriteria().',
)]
final class DocumentOrderCriteriaEvent extends Event implements GenericEvent
{
    private readonly string $name;

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function __construct(
        private readonly Criteria $criteria,
        private readonly Context $context,
        private readonly array $operations,
        private readonly DocumentRendererConfig $documentRendererConfig,
        string $documentType,
    ) {
        $this->name = $documentType . '.document.criteria';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, DocumentGenerateOperation> $operations
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getDocumentRendererConfig(): DocumentRendererConfig
    {
        return $this->documentRendererConfig;
    }
}
