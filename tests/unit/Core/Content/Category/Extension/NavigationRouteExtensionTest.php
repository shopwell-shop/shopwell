<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\Extension\NavigationRouteExtension;
use Shopwell\Core\Content\Category\SalesChannel\NavigationRouteResponse;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Examples\NavigationRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NavigationRouteExtension::class)]
class NavigationRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesNavigation(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new NavigationRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: NavigationRouteExtension::NAME,
            extension: new NavigationRouteExtension(
                'active-id',
                'root-id',
                new Request(),
                Generator::generateSalesChannelContext(),
                new Criteria(),
            ),
            function: static function () use (&$coreCalled): NavigationRouteResponse {
                $coreCalled = true;

                return new NavigationRouteResponse(new CategoryCollection([
                    (new CategoryEntity())->assign(['id' => 'core-category']),
                ]));
            },
        );

        static::assertFalse($coreCalled, 'The core navigation loading must be skipped when a subscriber resolves it.');
        static::assertSame(['example-category'], array_values($result->getCategories()->getIds()));
    }
}
