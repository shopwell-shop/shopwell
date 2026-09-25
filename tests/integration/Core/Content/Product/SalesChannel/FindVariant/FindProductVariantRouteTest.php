<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
class FindProductVariantRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $repository;

    private SalesChannelContext $context;

    private FindProductVariantRoute $findProductVariantRoute;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('product.repository');

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', false);

        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', TestDefaults::SALES_CHANNEL);

        $this->findProductVariantRoute = static::getContainer()->get(FindProductVariantRoute::class);

        $this->ids = new IdsCollection();

        $this->createProduct();

        parent::setUp();
    }

    public function testFindVariant(): void
    {
        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        $switched = $this->ids->get('Color');

        $result = $this->findProductVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );

        static::assertSame($this->ids->get('redXL'), $result->getFoundCombination()->getVariantId());
    }

    public function testFindToNotCombinable(): void
    {
        // update red-xl to inactive
        $this->repository->update(
            [
                ['id' => $this->ids->get('redXL'), 'active' => false],
            ],
            Context::createDefaultContext()
        );

        $switched = $this->ids->get('Color');

        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        // wished to switch to red-xl but this variant is not available (active = false).
        // should switch to next matching size
        $result = $this->findProductVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );

        static::assertSame($this->ids->get('redL'), $result->getFoundCombination()->getVariantId());
    }

    public function testFindSkipsHiddenCloseoutVariantsWhenConfigured(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $this->repository->update(
            [
                ['id' => $this->ids->get('redXL'), 'stock' => 0, 'isCloseout' => true],
            ],
            Context::createDefaultContext()
        );

        $switched = $this->ids->get('Color');

        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        $result = $this->findProductVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );

        static::assertSame($this->ids->get('redL'), $result->getFoundCombination()->getVariantId());
    }

    public function testFindNoCombinable(): void
    {
        $switched = $this->ids->get('new');

        $options = [
            $this->ids->get('new') => $this->ids->get('new'),
        ];

        $this->expectExceptionObject(ProductException::variantNotFound(
            $this->ids->get('base'),
            [$this->ids->get('new') => $this->ids->get('new')]
        ));

        $this->findProductVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );
    }

    private function createProduct(): void
    {
        (new ProductBuilder($this->ids, 'base', 10))->configuratorSetting(
            'Red',
            'Color'
        )->configuratorSetting(
            'Green',
            'Color'
        )->configuratorSetting(
            'XL',
            'Size'
        )->configuratorSetting(
            'L',
            'Size'
        )->visibility()->price(10)->write(static::getContainer());

        (new ProductBuilder($this->ids, 'redXL', 10))->visibility()->parent('base')->price(10)->option(
            'Red',
            'Color'
        )->option('XL', 'Size')->stock(10)->write(static::getContainer());
        (new ProductBuilder($this->ids, 'greenXL', 10))->visibility()->parent('base')->price(10)->option(
            'Green',
            'Color'
        )->option('XL', 'Size')->stock(10)->write(static::getContainer());
        (new ProductBuilder($this->ids, 'redL', 10))->visibility()->parent('base')->price(10)->option(
            'Red',
            'Color'
        )->option('L', 'Size')->stock(10)->write(static::getContainer());
        (new ProductBuilder($this->ids, 'greenL', 10))->visibility()->parent('base')->price(10)->option(
            'Green',
            'Color'
        )->option('L', 'Size')->stock(10)->write(static::getContainer());
    }
}
