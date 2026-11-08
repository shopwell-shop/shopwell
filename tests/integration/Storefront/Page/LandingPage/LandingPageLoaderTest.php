<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page\LandingPage;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Content\LandingPage\LandingPageException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Storefront\Page\LandingPage\LandingPage;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class LandingPageLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private IdsCollection $ids;

    public function testLoadWithoutId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));

        $context = $this->createSalesChannelContextWithNavigation();
        $this->getPageLoader()->load(new Request(), $context);
    }

    public function testLoad(): void
    {
        $this->ids = new IdsCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->get('landing-page'),
        ]);

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData();

        $event = null;
        $this->catchEvent(LandingPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(LandingPage::class, $page);

        static::assertInstanceOf(LandingPageEntity::class, $page->getLandingPage());

        self::assertPageEvent(LandingPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testLoadWithInactiveLandingPage(): void
    {
        $this->ids = new IdsCollection();

        $request = new Request([], [], [
            'landingPageId' => $this->ids->create('landing-page'),
        ]);
        $this->expectExceptionObject(LandingPageException::notFound($this->ids->get('landing-page')));

        $context = $this->createSalesChannelContextWithNavigation();
        $this->ids->set('sales-channel', $context->getSalesChannelId());
        $this->createData(false);

        $this->getPageLoader()->load($request, $context);
    }

    /**
     * @return LandingPageLoader
     */
    protected function getPageLoader()
    {
        return static::getContainer()->get(LandingPageLoader::class);
    }

    private function createData(bool $isActive = true): void
    {
        $data = [
            'id' => $this->ids->create('landing-page'),
            'name' => 'Test',
            'url' => 'myUrl',
            'active' => $isActive,
            'salesChannels' => [
                [
                    'id' => $this->ids->get('sales-channel'),
                ],
            ],
        ];

        static::getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
