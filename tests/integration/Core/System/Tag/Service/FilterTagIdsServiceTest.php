<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Tag\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Tag\Service\FilterTagIdsService;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class FilterTagIdsServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private FilterTagIdsService $filterTagIdsService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->filterTagIdsService = static::getContainer()->get(FilterTagIdsService::class);
    }

    public function testFilterIdsWithDuplicateFilter(): void
    {
        $this->prepareTestData();

        $request = new Request();
        $request->request->set('duplicateFilter', true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('landingPages.id', null));
        $criteria->addSorting(new FieldSorting('categories.name', FieldSorting::ASCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            $request,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame(5, $filteredTagIdsStruct->getTotal());
        static::assertSame(
            [
                $this->ids->get('a'),
                $this->ids->get('b'),
                $this->ids->get('c'),
                $this->ids->get('d'),
                $this->ids->get('e'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithEmptyFilter(): void
    {
        $this->prepareTestData();

        $request = new Request();
        $request->request->set('emptyFilter', true);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            $request,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame(2, $filteredTagIdsStruct->getTotal());
        static::assertSame(
            [
                $this->ids->get('unassigned'),
                $this->ids->get('unique'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithAggregatedSorting(): void
    {
        $this->prepareTestData();

        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('categories.id', null),
        ]));
        $criteria->addSorting(new CountSorting('categories.id', FieldSorting::DESCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            new Request(),
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame(5, $filteredTagIdsStruct->getTotal());
        static::assertSame(
            [
                $this->ids->get('e'),
                $this->ids->get('d'),
                $this->ids->get('c'),
                $this->ids->get('b'),
                $this->ids->get('a'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithAggregatedSortingWithInheritedAndVersionized(): void
    {
        $versionContext = $this->prepareTestDataWithInheritedAndVersionized();

        $criteria = new Criteria();
        $criteria->addSorting(new CountSorting('products.id', FieldSorting::DESCENDING));

        Context::createDefaultContext()->enableInheritance(function (Context $context) use ($criteria): void {
            $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
                new Request(),
                $criteria,
                $context
            );

            static::assertSame(2, $filteredTagIdsStruct->getTotal());
            static::assertSame(
                [
                    $this->ids->get('g'),
                    $this->ids->get('f'),
                ],
                $filteredTagIdsStruct->getIds()
            );
        });

        $criteria = new Criteria();
        $criteria->addSorting(new CountSorting('orders.id', FieldSorting::ASCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            new Request(),
            $criteria,
            $versionContext
        );

        static::assertSame(2, $filteredTagIdsStruct->getTotal());
        static::assertSame(
            [
                $this->ids->get('f'),
                $this->ids->get('g'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithAssignmentFilter(): void
    {
        $this->prepareTestData();
        $this->prepareTestDataWithInheritedAndVersionized();

        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $request = new Request();

        $request->request->set('assignmentFilter', ['categories']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertSame(5, $filteredTagIdsStruct->getTotal());

        $request->request->set('assignmentFilter', ['categories', 'orders']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertSame(6, $filteredTagIdsStruct->getTotal());

        $request->request->set('assignmentFilter', ['categories', 'products']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertSame(7, $filteredTagIdsStruct->getTotal());

        $request->request->set('assignmentFilter', ['invalid']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertSame(9, $filteredTagIdsStruct->getTotal());
    }

    private function prepareTestData(): void
    {
        $tags = [
            [
                'id' => $this->ids->get('a'),
                'name' => 'foo',
                'categories' => $this->getCategoryPayload(1, 'a'),
            ],
            [
                'id' => $this->ids->get('b'),
                'name' => 'bar',
                'categories' => $this->getCategoryPayload(2, 'b'),
            ],
            [
                'id' => $this->ids->get('c'),
                'name' => 'foo',
                'categories' => $this->getCategoryPayload(3, 'c'),
            ],
            [
                'id' => $this->ids->get('unique'),
                'name' => 'unique',
            ],
            [
                'id' => $this->ids->get('unassigned'),
                'name' => 'unassigned',
            ],
            [
                'id' => $this->ids->get('d'),
                'name' => 'foo',
                'categories' => $this->getCategoryPayload(4, 'd'),
            ],
            [
                'id' => $this->ids->get('e'),
                'name' => 'bar',
                'categories' => $this->getCategoryPayload(5, 'e'),
            ],
        ];

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        static::getContainer()->get('tag.repository')->create($tags, $context);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getCategoryPayload(int $count, string $name): array
    {
        $payload = [];

        for ($i = 0; $i < $count; ++$i) {
            $payload[] = ['name' => $name];
        }

        return $payload;
    }

    private function prepareTestDataWithInheritedAndVersionized(): Context
    {
        $context = Context::createDefaultContext();

        $products = [
            (new ProductBuilder($this->ids, 'p1'))
                ->price(100)
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'p2'))
                ->price(100)
                ->variant(
                    (new ProductBuilder($this->ids, 'v2.1'))
                        ->option('red', 'color')
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v2.2'))
                        ->option('green', 'color')
                        ->build()
                )
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'p3'))
                ->price(100)
                ->visibility()
                ->build(),
        ];

        static::getContainer()->get('product.repository')
            ->create($products, $context);

        $tags = [
            [
                'id' => $this->ids->get('f'),
                'name' => 'foo',
                'products' => [
                    ['id' => $this->ids->get('p2')],
                ],
            ],
            [
                'id' => $this->ids->get('g'),
                'name' => 'bar',
                'products' => [
                    ['id' => $this->ids->get('p1')],
                    ['id' => $this->ids->get('p3')],
                ],
            ],
        ];

        static::getContainer()->get('tag.repository')->create($tags, $context);

        $order = $this->getOrderFixture($this->ids->get('o1'), $context->getVersionId());

        static::getContainer()->get('order.repository')->create([$order], $context);

        $versionId = static::getContainer()->get('order.repository')->createVersion(
            $this->ids->get('o1'),
            $context,
            Uuid::randomHex(),
            Uuid::randomHex()
        );

        $versionContext = Context::createDefaultContext()->createWithVersionId($versionId);

        $orders = [
            [
                'id' => $this->ids->get('o1'),
                'tags' => [
                    ['id' => $this->ids->get('g')],
                ],
            ],
        ];

        static::getContainer()->get('order.repository')->update($orders, $versionContext);

        return $versionContext;
    }

    /**
     * @return array<string, mixed>
     */
    private function getOrderFixture(string $orderId, string $orderVersionId): array
    {
        $stateId = static::getContainer()->get('state_machine_state.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('stateMachine.technicalName', OrderStates::STATE_MACHINE)), Context::createDefaultContext())
            ->firstId();
        static::assertIsString($stateId);

        return [
            'id' => $orderId,
            'versionId' => $orderVersionId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'customerId' => Uuid::randomHex(),
            'billingAddressId' => Uuid::randomHex(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.00,
            'price' => [
                'netPrice' => 1000.00,
                'totalPrice' => 1000.00,
                'positionPrice' => 1000.00,
                'calculatedTaxes' => [
                    [
                        'tax' => 0.0,
                        'taxRate' => 0.0,
                        'price' => 0.00,
                        'extensions' => [],
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 0.0,
                        'extensions' => [],
                        'percentage' => 100.0,
                    ],
                ],
                'taxStatus' => 'gross',
                'rawTotal' => 1000.00,
            ],
            'shippingCosts' => [
                'unitPrice' => 0.0,
                'totalPrice' => 0.0,
                'listPrice' => null,
                'referencePrice' => null,
                'quantity' => 1,
                'calculatedTaxes' => [
                    [
                        'tax' => 0.0,
                        'taxRate' => 0.0,
                        'price' => 0.0,
                        'extensions' => [],
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 0.0,
                        'extensions' => [],
                        'percentage' => 100,
                    ],
                ],
            ],
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'stateId' => $stateId,
            'orderDateTime' => new \DateTime(),
        ];
    }
}
