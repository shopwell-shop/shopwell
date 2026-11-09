<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Page\Navigation\Error\ErrorPageLoadedEvent;
use Shopwell\Storefront\Page\Navigation\Error\ErrorPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class ErrorPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    /**
     * @var EntityRepository<ContentLayoutCollection>
     */
    private EntityRepository $contentLayoutRepository;

    private string $errorLayoutId;

    protected function setUp(): void
    {
        parent::setUp();

        $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        $this->contentLayoutRepository = static::getContainer()->get('content_layout.repository');
        $this->salesChannelContext = $contextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->errorLayoutId = $this->createPage();
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.http404Page', $this->errorLayoutId);
    }

    public function testItDoesLoad404ContentLayoutPageIn404Case(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(ErrorPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($this->errorLayoutId, $request, $context);

        self::assertPageEvent(ErrorPageLoadedEvent::class, $event, $context, $request, $page);
        static::assertSame('404 layout', $page->getContentPage()?->name);
    }

    protected function getPageLoader(): ErrorPageLoader
    {
        return static::getContainer()->get(ErrorPageLoader::class);
    }

    private function createPage(): string
    {
        $page = [
            'id' => Uuid::randomHex(),
            'name' => '404 layout',
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [[
                'id' => Uuid::randomHex(),
                'component' => 'Sw:Content:Text',
                'properties' => ['text' => '<p>Page not found</p>'],
            ]],
        ];

        $this->contentLayoutRepository->create([$page], $this->salesChannelContext->getContext());

        return $page['id'];
    }
}
