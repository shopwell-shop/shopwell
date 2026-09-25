<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Renderer;

use Shopwell\Core\Checkout\DocumentV2\DocumentFormat;
use Shopwell\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopwell\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopwell\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopwell\Core\Checkout\DocumentV2\Template\PaginationCounter;
use Shopwell\Core\Checkout\DocumentV2\Template\TemplateContext;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Renders the HTML representation of a document via {@see DocumentTemplateRenderer}.
 *
 * The output doubles as the {@see PdfRenderer} Dompdf input, so browser-only styling
 * is scoped to `media="screen"` in the templates.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class HtmlRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::HTML;

    private const TEMPLATE_PATTERN = '@Framework/documents/%s.html.twig';

    public function __construct(
        private DocumentTemplateRenderer $documentTemplateRenderer,
    ) {
    }

    public function getFormat(): string
    {
        return self::FORMAT->value;
    }

    public function getFileExtension(): string
    {
        return self::FORMAT->fileExtension();
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        $meta = $input->requireData(
            DocumentMetaProvider::KEY,
            DocumentMetaRenderData::class,
        );

        $typeData = $input->getAllData();
        unset($typeData[DocumentMetaProvider::KEY]);

        $configuration = new TemplateContext($meta, $typeData);

        $template = \sprintf(self::TEMPLATE_PATTERN, $input->documentType);

        $content = $this->documentTemplateRenderer->render(
            $template,
            $input,
            $context,
            [
                'config' => $configuration,
                'counter' => new PaginationCounter(),
            ],
        );

        $fileStem = $meta->config->buildFileStem($meta->documentNumber, self::FORMAT->value);

        return new RenderResult(
            format: self::FORMAT->value,
            content: $content,
            fileName: $fileStem,
            fileExtension: self::FORMAT->fileExtension(),
            mimeType: self::FORMAT->mimeType(),
        );
    }
}
