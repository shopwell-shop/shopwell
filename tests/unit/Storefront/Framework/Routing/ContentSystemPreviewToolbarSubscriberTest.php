<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Framework\Routing\ContentSystemPreviewToolbarSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemPreviewToolbarSubscriber::class)]
class ContentSystemPreviewToolbarSubscriberTest extends TestCase
{
    #[DataProvider('responseProvider')]
    public function testRemovesOnlyCompletePreviewToolbarBlocks(string $route, int $requestType, string $body, string $expected): void
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $response = new Response($body);
        $event = new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, $requestType, $response);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ContentSystemPreviewToolbarSubscriber());

        $dispatcher->dispatch($event, KernelEvents::RESPONSE);

        static::assertSame($expected, $response->getContent());
    }

    /**
     * @return iterable<string, array{string, int, string, string}>
     */
    public static function responseProvider(): iterable
    {
        $toolbar = "<!-- START of Symfony Web Debug Toolbar -->\n<script>toolbar()</script>\n<!-- END of Symfony Web Debug Toolbar -->";

        yield 'main preview removes multiline toolbar' => ['frontend.content-system.preview', HttpKernelInterface::MAIN_REQUEST, '<main>preview</main>' . $toolbar, '<main>preview</main>'];
        yield 'two toolbars preserve the content between them' => ['frontend.content-system.preview', HttpKernelInterface::MAIN_REQUEST, $toolbar . '<main>preview</main>' . $toolbar, '<main>preview</main>'];
        yield 'ordinary storefront retains its toolbar' => ['frontend.home.page', HttpKernelInterface::MAIN_REQUEST, $toolbar, $toolbar];
        yield 'preview subrequest is untouched' => ['frontend.content-system.preview', HttpKernelInterface::SUB_REQUEST, $toolbar, $toolbar];
        yield 'empty response stays empty' => ['frontend.content-system.preview', HttpKernelInterface::MAIN_REQUEST, '', ''];
        yield 'ordinary comments remain' => ['frontend.content-system.preview', HttpKernelInterface::MAIN_REQUEST, '<!-- app comment --><main>preview</main>', '<!-- app comment --><main>preview</main>'];
        yield 'unclosed toolbar marker does not consume the page' => ['frontend.content-system.preview', HttpKernelInterface::MAIN_REQUEST, '<!-- START of Symfony Web Debug Toolbar --><main>preview</main>', '<!-- START of Symfony Web Debug Toolbar --><main>preview</main>'];
    }
}
