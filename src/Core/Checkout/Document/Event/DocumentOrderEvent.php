<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Event;

use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopwell\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
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
    description: 'Base class of the per-type order events. Register a data provider for the affected document type instead.',
)]
abstract class DocumentOrderEvent extends Event
{
    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function __construct(
        private readonly OrderCollection $orders,
        private readonly Context $context,
        private readonly array $operations = []
    ) {
    }

    /**
     * @return array<string, DocumentGenerateOperation> $operations
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrders(): OrderCollection
    {
        return $this->orders;
    }
}
