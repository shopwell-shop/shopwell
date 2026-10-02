<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Document\DocumentException;
use Shopwell\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopwell\Core\Checkout\Document\Service\HtmlRenderer;
use Shopwell\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFileRendererRegistry::class)]
class DocumentFileRendererRegistryTest extends TestCase
{
    #[DataProvider('documentTypeRendererProvider')]
    public function testRender(RenderedDocument $document, \Closure $expectsClosure): void
    {
        $registry = $this->createMock(DocumentFileRendererRegistry::class);
        $registry
            ->expects($this->exactly(1))
            ->method('render')
            ->willReturn($document->getContent());

        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setLocale($locale);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId($language->getId());
        $order->setLanguage($language);

        $document->setOrder($order);
        $document->setContext(Context::createDefaultContext());

        $content = $registry->render($document);

        $expectsClosure($content);
    }

    public function testThrowException(): void
    {
        $this->expectExceptionObject(DocumentException::unsupportedDocumentFileExtension('xml'));

        $registry = new DocumentFileRendererRegistry([]);

        $registry->render(new RenderedDocument(
            '1001',
            'invoice',
            'xml',
            [],
            'application/xml'
        ));
    }

    public static function documentTypeRendererProvider(): \Generator
    {
        yield 'PDF renderer' => [
            new RenderedDocument(
                number: '1001',
                name: 'invoice',
                content: 'pdf'
            ),

            static function (string $rendered): void {
                static::assertSame($rendered, 'pdf');
            },
        ];

        yield 'HTML renderer' => [
            new RenderedDocument(
                number: '1001',
                name: 'invoice',
                fileExtension: HtmlRenderer::FILE_EXTENSION,
                config: [],
                contentType: HtmlRenderer::FILE_CONTENT_TYPE,
                content: 'html'
            ),

            static function (string $rendered): void {
                static::assertSame($rendered, 'html');
            },
        ];
    }
}
