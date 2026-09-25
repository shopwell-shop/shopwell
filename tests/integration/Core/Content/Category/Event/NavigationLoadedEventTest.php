<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Category\Event;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\Event\NavigationLoadedEvent;
use Shopwell\Core\Content\Category\Service\NavigationLoader;
use Shopwell\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class NavigationLoadedEventTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected NavigationLoaderInterface $loader;

    protected function setUp(): void
    {
        $this->loader = static::getContainer()->get(NavigationLoader::class);
        parent::setUp();
    }

    public function testEventDispatched(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $this->addEventListener($dispatcher, NavigationLoadedEvent::class, $listener);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        $this->loader->load($navigationId, $context, $navigationId);
    }
}
