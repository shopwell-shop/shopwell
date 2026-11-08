<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(NavigationPageSeoUrlRoute::class)]
class NavigationPageSeoUrlRouteTest extends TestCase
{
    public function testPrepareCriteria(): void
    {
        $navigationPageSeoUrlRoute = new NavigationPageSeoUrlRoute(
            new CategoryDefinition(),
            static::createStub(CategoryBreadcrumbBuilder::class)
        );

        $salesChannel = new SalesChannelEntity();

        $criteria = new Criteria();
        $navigationPageSeoUrlRoute->prepareCriteria($criteria, $salesChannel);

        $filters = $criteria->getFilters();
        $multiFilter = $filters[0];
        static::assertInstanceOf(MultiFilter::class, $multiFilter);
        static::assertSame('AND', $multiFilter->getOperator());
        $multiFilterQueries = $multiFilter->getQueries();

        static::assertCount(2, $multiFilterQueries);
        static::assertInstanceOf(EqualsFilter::class, $multiFilterQueries[0]);
        $this->assertEqualsFilter(
            $multiFilterQueries[0],
            'active',
            true
        );

        $notFilter = $multiFilterQueries[1];
        static::assertInstanceOf(NotFilter::class, $notFilter);
        static::assertSame('OR', $notFilter->getOperator());

        $notFilterQueries = $notFilter->getQueries();
        static::assertCount(1, $notFilterQueries);
        static::assertInstanceOf(EqualsFilter::class, $notFilterQueries[0]);
        $this->assertEqualsFilter(
            $notFilterQueries[0],
            'type',
            CategoryDefinition::TYPE_FOLDER
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function mappingErrorProvider(): iterable
    {
        yield 'page category gets a SEO URL' => [CategoryDefinition::TYPE_PAGE, null];
        yield 'link category keeps its existing SEO URLs but gets no new one' => [
            CategoryDefinition::TYPE_LINK,
            'Link categories redirect to their target and do not get new SEO URLs',
        ];
    }

    #[DataProvider('mappingErrorProvider')]
    public function testGetMappingReportsAnErrorForLinkCategories(string $type, ?string $expectedError): void
    {
        $navigationId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId($navigationId);

        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setPath('|' . $navigationId . '|');
        $category->setType($type);

        $mapping = $this->createRoute()->getMapping($category, $salesChannel);

        static::assertSame($expectedError, $mapping->getError());
    }

    public function testGetMappingReportsMissingSalesChannelBeforeLinkType(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId(Uuid::randomHex());

        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setPath('|' . Uuid::randomHex() . '|');
        $category->setType(CategoryDefinition::TYPE_LINK);

        $mapping = $this->createRoute()->getMapping($category, $salesChannel);

        static::assertSame('Category is not available for sales channel', $mapping->getError());
    }

    private function createRoute(): NavigationPageSeoUrlRoute
    {
        return new NavigationPageSeoUrlRoute(
            new CategoryDefinition(),
            static::createStub(CategoryBreadcrumbBuilder::class)
        );
    }

    private function assertEqualsFilter(
        EqualsFilter $equalsFilter,
        string $field,
        string|bool $value
    ): void {
        static::assertSame($field, $equalsFilter->getField());
        static::assertSame($value, $equalsFilter->getValue());
    }
}
