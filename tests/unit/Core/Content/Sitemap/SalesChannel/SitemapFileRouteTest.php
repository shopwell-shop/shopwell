<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Sitemap\SalesChannel;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapFileRoute;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Tests\Examples\GetSitemapFileExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SitemapFileRoute::class)]
class SitemapFileRouteTest extends TestCase
{
    public function testExtension(): void
    {
        $fileSystem = static::createStub(FilesystemOperator::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new GetSitemapFileExample());

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $route = new SitemapFileRoute($fileSystem, $extensionDispatcher);

        $request = new Request();
        $context = static::createStub(SalesChannelContext::class);
        $filePath = 'test.xml.gz';

        $response = $route->getSitemapFile($request, $context, $filePath);

        static::assertSame('Hello World!', $response->getContent());
    }
}
