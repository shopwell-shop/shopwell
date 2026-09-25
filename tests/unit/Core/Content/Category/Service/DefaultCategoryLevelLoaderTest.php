<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DefaultCategoryLevelLoader::class)]
class DefaultCategoryLevelLoaderTest extends TestCase
{
    private DefaultCategoryLevelLoader $categoryLevelLoader;

    /**
     * @var MockObject&SalesChannelRepository<CategoryCollection>
     */
    private MockObject&SalesChannelRepository $categoryRepository;

    private Stub&SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(SalesChannelRepository::class);
        $this->salesChannelContext = static::createStub(SalesChannelContext::class);

        $this->categoryLevelLoader = new DefaultCategoryLevelLoader(
            $this->categoryRepository
        );
    }

    public function testLoadLevels(): void
    {
        $rootId = 'non-navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $salesChannel = (new SalesChannelEntity())->assign([
            'navigationCategoryId' => 'different-id',
        ]);

        $this->salesChannelContext->method('getSalesChannel')
            ->willReturn($salesChannel);

        $expectedCollection = new CategoryCollection();

        $this->categoryRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'category',
                0,
                $expectedCollection,
                null,
                $criteria,
                $this->salesChannelContext->getContext()
            ));

        $result = $this->categoryLevelLoader->loadLevels(
            $rootId,
            $rootLevel,
            $this->salesChannelContext,
            $criteria,
            $depth
        );

        static::assertSame($expectedCollection, $result);
    }
}
