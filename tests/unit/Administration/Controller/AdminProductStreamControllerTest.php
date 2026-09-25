<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Controller\AdminProductStreamController;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminProductStreamController::class)]
class AdminProductStreamControllerTest extends TestCase
{
    private MockObject&RequestCriteriaBuilder $requestCriteriaBuilder;

    private Stub&SalesChannelContextServiceInterface $salesChannelContextService;

    /**
     * @var MockObject&SalesChannelRepository<ProductCollection>
     */
    private MockObject&SalesChannelRepository $salesChannelRepository;

    private Stub&ProductDefinition $productDefinition;

    protected function setUp(): void
    {
        $this->productDefinition = static::createStub(ProductDefinition::class);
        $this->salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $this->salesChannelContextService = static::createStub(SalesChannelContextServiceInterface::class);
        $this->requestCriteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
    }

    public function testProductStreamPreview(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());

        $this->salesChannelRepository->expects($this->once())->method('search')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $context) {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($criteria->hasAssociation('options'));
                static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(ProductAvailableFilter::class, $criteria->getFilters()[0]);
                static::assertCount(0, $criteria->getGroupFields());

                return new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $controller->productStreamPreview('salesChannelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString(
            '{"extensions":[],"elements":[],"aggregations":[],"page":1,"limit":null,"entity":"product","total":1,"states":[]}',
            $response->getContent()
        );
    }

    public function testProductStreamPreviewAppliesGroupingWhenDisplayAsGroupRequested(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());

        $this->salesChannelRepository->expects($this->once())->method('search')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $context) {
                // grouping mirrors the storefront listing: group by displayGroup and drop the null group
                static::assertContainsEquals(new FieldGrouping('displayGroup'), $criteria->getGroupFields());
                static::assertContainsEquals(new NotEqualsFilter('displayGroup', null), $criteria->getFilters());

                return new EntitySearchResult(
                    'product',
                    0,
                    new ProductCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $controller->productStreamPreview(
            'salesChannelId',
            new Request(['displayAsGroup' => '1']),
            $context
        );

        static::assertNotFalse($response->getContent());
    }
}
