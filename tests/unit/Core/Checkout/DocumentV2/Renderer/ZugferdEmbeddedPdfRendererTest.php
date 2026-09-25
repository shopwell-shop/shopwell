<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopwell\Core\Checkout\DocumentV2\DocumentFormat;
use Shopwell\Core\Checkout\DocumentV2\DocumentType;
use Shopwell\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopwell\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopwell\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopwell\Core\Checkout\DocumentV2\Renderer\ZugferdEmbeddedPdfRenderer;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdEmbeddedPdfRenderer::class)]
class ZugferdEmbeddedPdfRendererTest extends TestCase
{
    public function testConfig(): void
    {
        $renderer = new ZugferdEmbeddedPdfRenderer('version');

        static::assertSame(DocumentFormat::ZUGFERD_EMBEDDED_PDF->value, $renderer->getFormat());
        static::assertSame(
            [DocumentFormat::PDF->value, DocumentFormat::ZUGFERD_XML->value],
            $renderer->getDependencies(),
        );
    }

    public function testThrowsWhenMetaRenderDataMissing(): void
    {
        $renderer = new ZugferdEmbeddedPdfRenderer('version');

        $input = new RenderInput(DocumentType::INVOICE->value, '12345', $this->createOrder(), []);

        $this->expectExceptionObject(
            DocumentV2Exception::unknownRenderData(DocumentMetaProvider::KEY, DocumentMetaRenderData::class),
        );

        $renderer->renderToString($input, new RenderState(), Context::createDefaultContext());
    }

    public function testThrowsWhenPdfDependencyMissing(): void
    {
        $renderer = new ZugferdEmbeddedPdfRenderer('version');

        $state = new RenderState();
        $state->add($this->renderResult(DocumentFormat::ZUGFERD_XML, '<invoice/>'));

        $this->expectExceptionObject(
            DocumentV2Exception::unknownRenderResult(DocumentFormat::PDF->value),
        );

        $renderer->renderToString($this->createInput(), $state, Context::createDefaultContext());
    }

    public function testThrowsWhenXmlDependencyMissing(): void
    {
        $renderer = new ZugferdEmbeddedPdfRenderer('version');

        $state = new RenderState();
        $state->add($this->renderResult(DocumentFormat::PDF, '%PDF-1.7'));

        $this->expectExceptionObject(
            DocumentV2Exception::unknownRenderResult(DocumentFormat::ZUGFERD_XML->value),
        );

        $renderer->renderToString($this->createInput(), $state, Context::createDefaultContext());
    }

    public function testWrapsMergerFailureAsDomainException(): void
    {
        $renderer = new ZugferdEmbeddedPdfRenderer('version');

        $state = new RenderState();
        $state->add($this->renderResult(DocumentFormat::PDF, 'not-a-pdf'));
        $state->add($this->renderResult(DocumentFormat::ZUGFERD_XML, 'not-xml'));

        try {
            $renderer->renderToString($this->createInput(), $state, Context::createDefaultContext());
            static::fail('Expected DocumentV2Exception to be thrown.');
        } catch (DocumentV2Exception $e) {
            static::assertSame(DocumentV2Exception::EMBED_FAILED, $e->getErrorCode());
            static::assertNotNull($e->getPrevious());
        }
    }

    private function createInput(): RenderInput
    {
        return new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $this->createOrder(),
            [DocumentMetaProvider::KEY => $this->createMeta()],
        );
    }

    private function createMeta(): DocumentMetaRenderData
    {
        return new DocumentMetaRenderData(
            config: new DocumentConfig(
                pageSize: 'a4',
                pageOrientation: 'portrait',
                itemsPerPage: 10,
                filenamePrefix: 'invoice_',
            ),
            company: new DocumentCompanyInfo(
                'company',
                'street',
                '12345',
                'city',
                new CountryEntity(),
            ),
            display: new DocumentDisplayOptions(),
            documentDate: 'date',
            documentNumber: '12345',
            documentComment: null,
        );
    }

    private function renderResult(DocumentFormat $format, string $content): RenderResult
    {
        return new RenderResult(
            format: $format->value,
            content: $content,
            fileName: 'invoice_12345',
            fileExtension: $format->fileExtension(),
            mimeType: $format->mimeType(),
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        return $order;
    }
}
