<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Extension;

use Shopwell\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Rendering of the PDF document
 *
 * @description This event allows manipulation of the input and output when rendering PDF documents.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<string>
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: AbstractDocumentRenderer::class,
    description: 'DocumentV2 ships its own HTML, PDF and ZUGFeRD renderers. Implement AbstractDocumentRenderer to add a custom output format.',
)]
final class PdfRendererExtension extends Extension
{
    public const NAME = 'pdf-renderer';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(public readonly RenderedDocument $document)
    {
    }
}
