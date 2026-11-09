<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Shopwell\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopwell\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopwell\Core\Framework\ContentSystem\LayoutReference;
use Shopwell\Core\Framework\ContentSystem\Output\RenderResult;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\ContentSystemPreviewController;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemPreviewController::class)]
class ContentSystemPreviewControllerTest extends TestCase
{
    private ContentPreviewPayloadStore $store;

    protected function setUp(): void
    {
        $this->store = new ContentPreviewPayloadStore(new ArrayAdapter(), Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }

    public function testUnknownTokenIsNotFound(): void
    {
        $controller = new PreviewControllerRecorder($this->store, static::createStub(ContentPreviewPageBuilder::class), static::createStub(Connection::class));
        $this->expectExceptionObject(new NotFoundHttpException('Preview token not found or expired.'));

        $controller->preview('unknown-token', new Request(), Generator::generateSalesChannelContext());
    }

    #[DataProvider('previewProvider')]
    public function testPreviewUsesResolvedContextAndConstrainsEmbedding(?string $referer, string|false $themeId, string $expectedOrigin, ?string $expectedThemeId): void
    {
        $incomingContext = Generator::generateSalesChannelContext();
        $resolvedContext = Generator::generateSalesChannelContext();
        $payload = new ContentPreviewRequest([], 'product', 'product-id', $resolvedContext->getSalesChannelId(), queryParameters: ['page' => 2]);
        $token = $this->store->store($payload);
        $receivedPayload = null;
        $receivedContext = null;
        $result = new RenderResult([], LayoutReference::create('preview-layout', 'Preview layout', null), null);
        $builder = static::createStub(ContentPreviewPageBuilder::class);
        $builder->method('build')->willReturnCallback(static function (ContentPreviewRequest $request, Context $context) use (&$receivedPayload, &$receivedContext, $result, $resolvedContext): array {
            $receivedPayload = $request;
            $receivedContext = $context;

            return ['result' => $result, 'salesChannelContext' => $resolvedContext];
        });
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);
        $controller = new PreviewControllerRecorder($this->store, $builder, $connection);
        $request = Request::create('https://store.example/content-system/preview/' . $token);
        if ($referer !== null) {
            $request->headers->set('referer', $referer);
        }

        $response = $controller->preview($token, $request, $incomingContext);

        static::assertEquals($payload, $receivedPayload);
        static::assertSame($incomingContext->getContext(), $receivedContext);
        static::assertSame($resolvedContext->getSalesChannelId(), $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));
        static::assertSame($resolvedContext->getContext(), $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT));
        static::assertSame($resolvedContext, $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
        static::assertSame($expectedThemeId, $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID));
        static::assertSame('@Storefront/storefront/page/content/preview.html.twig', $controller->view);
        $page = $controller->parameters['contentPage'];
        static::assertInstanceOf(ContentPage::class, $page);
        static::assertSame('preview-layout', $page->id);
        static::assertSame('Preview layout', $page->name);
        static::assertSame([], $page->elements);
        static::assertSame([], $controller->parameters['headerParameters']);
        static::assertSame('<main>preview</main>', $response->getContent());
        static::assertSame('frame-ancestors \'self\' ' . $expectedOrigin . ';', $response->headers->get('Content-Security-Policy'));
        static::assertSame('ALLOWALL', $response->headers->get(PlatformRequest::HEADER_FRAME_OPTIONS));
    }

    /**
     * @return iterable<string, array{string|null, string|false, string, string|null}>
     */
    public static function previewProvider(): iterable
    {
        yield 'referer path and query are excluded from origin' => ['https://admin.example/admin?secret=value', '0123456789abcdef0123456789abcdef', 'https://admin.example', '0123456789abcdef0123456789abcdef'];
        yield 'explicit referer port is preserved' => ['http://admin.example:8080/admin', false, 'http://admin.example:8080', null];
        yield 'missing referer falls back to storefront origin' => [null, false, 'https://store.example', null];
        yield 'empty referer falls back to storefront origin' => ['', false, 'https://store.example', null];
        yield 'relative referer falls back to storefront origin' => ['/admin', false, 'https://store.example', null];
        yield 'referer without scheme falls back to storefront origin' => ['//admin.example/admin', false, 'https://store.example', null];
        yield 'invalid theme id is not assigned' => ['https://admin.example/admin', 'invalid-theme', 'https://admin.example', null];
        yield 'non hexadecimal theme id is not assigned' => ['https://admin.example/admin', 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz', 'https://admin.example', null];
    }
}

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class PreviewControllerRecorder extends ContentSystemPreviewController
{
    public ?string $view = null;

    /**
     * @var array<string, mixed>
     */
    public array $parameters = [];

    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $this->view = $view;
        $this->parameters = $parameters;

        return new Response('<main>preview</main>');
    }
}
