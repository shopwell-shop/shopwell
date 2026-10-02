<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\CmsException;
use Shopwell\Core\Content\Cms\CmsPageCollection;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Cms\Extension\CmsRouteExtension;
use Shopwell\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopwell\Core\Content\Cms\SalesChannel\CmsRouteResponse;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsRoute::class)]
class CmsRouteTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testGetDecorated(): void
    {
        $pageLoader = static::createStub(SalesChannelCmsPageLoaderInterface::class);
        $route = new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher()));

        $this->expectException(DecorationPatternException::class);
        $route->getDecorated();
    }

    public function testLoadHandlesSlotsAsArray(): void
    {
        $slots = [
            $this->ids->get('slot-1'),
            $this->ids->get('slot-2'),
            $this->ids->get('slot-3'),
        ];

        $request = new Request([
            'slots' => $slots,
        ]);

        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = $this->getExpectedCriteria($slots);
        $context = Generator::generateSalesChannelContext();

        $pageLoader = static::createStub(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->willReturn($searchResult);

        $actualCmsPage = (new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher())))->load($this->ids->get('cms-page'), $request, $context)->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadHandlesSlotsAsString(): void
    {
        $expectedSlots = [
            $this->ids->get('slot-1'),
            $this->ids->get('slot-2'),
            $this->ids->get('slot-3'),
        ];

        $request = new Request([
            'slots' => "{$this->ids->get('slot-1')}|{$this->ids->get('slot-2')}|{$this->ids->get('slot-3')}",
        ]);

        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = $this->getExpectedCriteria($expectedSlots);
        $context = Generator::generateSalesChannelContext();

        $pageLoader = static::createStub(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->willReturn($searchResult);

        $actualCmsPage = (new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher())))->load($this->ids->get('cms-page'), $request, $context)->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadCmsPageWithoutProvidedSlots(): void
    {
        $request = new Request([]);
        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = new Criteria([$this->ids->get('cms-page')]);
        $context = Generator::generateSalesChannelContext();

        $pageLoader = static::createStub(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->willReturn($searchResult);

        $actualCmsPage = (new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher())))->load($this->ids->get('cms-page'), $request, $context)->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadThrowsExceptionIfNoPageFound(): void
    {
        $request = new Request([]);

        // empty search result
        $searchResult = $this->getSearchResult();

        $cmsPageId = $this->ids->get('cms-page');
        $criteria = new Criteria([$cmsPageId]);
        $context = Generator::generateSalesChannelContext();

        $pageLoader = static::createStub(SalesChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->method('load')
            ->willReturn($searchResult);

        $route = new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher()));

        $this->expectExceptionObject(CmsException::pageNotFound($cmsPageId));
        $route->load($cmsPageId, $request, $context);
    }

    public function testPublishesExtension(): void
    {
        $id = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $response = static::createStub(CmsRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cms-route.load.pre', static function (CmsRouteExtension $extension) use ($id, $request, $context, $response): void {
            static::assertSame(['id' => $id, 'request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CmsRoute(
            static::createStub(SalesChannelCmsPageLoaderInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($id, $request, $context));
    }

    /**
     * @param array<string> $slots
     */
    private function getExpectedCriteria(array $slots): Criteria
    {
        $criteria = new Criteria([$this->ids->get('cms-page')]);
        $criteria
            ->getAssociation('sections.blocks')
            ->addFilter(new EqualsAnyFilter('slots.id', $slots));

        return $criteria;
    }

    /**
     * @return EntitySearchResult<CmsPageCollection>
     */
    private function getSearchResult(?CmsPageEntity $cmsPage = null): EntitySearchResult
    {
        $collection = new CmsPageCollection();
        if ($cmsPage !== null) {
            $cmsPage->setUniqueIdentifier('cms-page');
            $collection->add($cmsPage);
        }

        $searchResult = static::createStub(EntitySearchResult::class);

        $searchResult
            ->method('getEntities')
            ->willReturn($collection);

        return $searchResult;
    }
}
