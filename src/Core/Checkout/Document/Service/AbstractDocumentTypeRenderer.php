<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Service;

use Shopwell\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopwell\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: AbstractDocumentRenderer::class,
    description: 'DocumentV2 ships its own HTML, PDF and ZUGFeRD renderers. Implement AbstractDocumentRenderer to add a custom output format.',
)]
abstract class AbstractDocumentTypeRenderer
{
    abstract public function getContentType(): string;

    abstract public function render(RenderedDocument $document): string;

    abstract public function getDecorated(): AbstractDocumentTypeRenderer;
}
