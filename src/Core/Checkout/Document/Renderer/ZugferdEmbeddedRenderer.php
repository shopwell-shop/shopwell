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
    description: 'Register a data provider for DocumentType::INVOICE. Enrich the order criteria via enrichOrderCriteria() and the render data via provideRenderingData().',
)]
class ZugferdEmbeddedRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_embedded_invoice';

    /**
     * @internal
     */
    public function __construct(
        protected AbstractDocumentRenderer $invoiceRenderer,
        protected AbstractDocumentRenderer $electronicRenderer,
        protected ZugferdEmbeddedService $zugferdEmbeddedService,
        protected string $shopwellVersion,
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
        $invoice = $this->invoiceRenderer->render($operations, $context, $rendererConfig);

        return $this->zugferdEmbeddedService->embed(
            $operations,
            $context,
            $rendererConfig,
            $invoice,
            $this->electronicRenderer,
            $this->shopwellVersion
        );
    }
}
