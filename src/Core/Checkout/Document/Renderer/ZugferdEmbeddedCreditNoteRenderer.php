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
    description: 'Register a data provider for DocumentType::CREDIT_NOTE. Enrich the order criteria via enrichOrderCriteria() and the render data via provideRenderingData().',
)]
class ZugferdEmbeddedCreditNoteRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_embedded_credit_note';

    /**
     * @internal
     */
    public function __construct(
        protected AbstractDocumentRenderer $creditNoteRenderer,
        protected AbstractDocumentRenderer $zugferdCreditNoteRenderer,
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
        $creditNote = $this->creditNoteRenderer->render(
            $operations,
            $context,
            $rendererConfig
        );

        return $this->zugferdEmbeddedService->embed(
            $operations,
            $context,
            $rendererConfig,
            $creditNote,
            $this->zugferdCreditNoteRenderer,
            $this->shopwareVersion,
        );
    }
}
