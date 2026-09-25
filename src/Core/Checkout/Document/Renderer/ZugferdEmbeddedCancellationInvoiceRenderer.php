<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Renderer;

use Shopwell\Core\Checkout\Document\Service\ZugferdEmbeddedService;
use Shopwell\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: AbstractDocumentDataProvider::class,
    description: 'Register a data provider for DocumentType::CANCELLATION_INVOICE. Enrich the order criteria via enrichOrderCriteria() and the render data via provideRenderingData().',
)]
class ZugferdEmbeddedCancellationInvoiceRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_embedded_cancellation_invoice';

    /**
     * @internal
     */
    public function __construct(
        protected AbstractDocumentRenderer $cancellationInvoiceRenderer,
        protected AbstractDocumentRenderer $zugferdCancellationInvoiceRenderer,
        protected ZugferdEmbeddedService $zugferdEmbeddedService,
        protected string $shopwareVersion,
    ) {
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $cancellationInvoice = $this->cancellationInvoiceRenderer->render(
            $operations,
            $context,
            $rendererConfig
        );

        return $this->zugferdEmbeddedService->embed(
            $operations,
            $context,
            $rendererConfig,
            $cancellationInvoice,
            $this->zugferdCancellationInvoiceRenderer,
            $this->shopwareVersion
        );
    }
}
