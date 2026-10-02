<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Language\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\Extension\LanguageRouteExtension;
use Shopwell\Core\System\Language\SalesChannel\LanguageRoute;
use Shopwell\Core\System\Language\SalesChannel\LanguageRouteResponse;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(LanguageRoute::class)]
class LanguageRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(LanguageRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('language-route.load.pre', static function (LanguageRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new LanguageRoute(
            static::createStub(SalesChannelRepository::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }
}
