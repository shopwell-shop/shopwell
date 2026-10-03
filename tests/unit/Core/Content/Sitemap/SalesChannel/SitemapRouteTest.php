<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Sitemap\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Sitemap\Extension\SitemapRouteExtension;
use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapRouteResponse;
use Shopwell\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopwell\Core\Content\Sitemap\Service\SitemapListerInterface;
use Shopwell\Core\Content\Sitemap\Struct\SitemapCollection;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SitemapRoute::class)]
class SitemapRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $response = new SitemapRouteResponse(new SitemapCollection());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('sitemap-route.load.pre', static function (SitemapRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SitemapRoute(
            static::createStub(SitemapListerInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(SitemapExporterInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context));
    }
}
