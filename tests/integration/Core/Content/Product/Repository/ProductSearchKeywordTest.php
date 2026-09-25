<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\Repository;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSearchKeywordTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testAddProductWithSearchKeyword(): void
    {
        $id = Uuid::randomHex();

        $this->createProduct($id, ['YTN', 'Search Keyword']);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->getEntities()
            ->get($id);

        $customSearchKeywords = $product->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword', $customSearchKeywords);
    }

    public function testEditProductWithSearchKeyword(): void
    {
        $id = Uuid::randomHex();

        $this->createProduct($id, ['YTN']);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->getEntities()
            ->get($id);

        $customSearchKeywords = $product->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);

        $update = [
            'id' => $id,
            'customSearchKeywords' => ['YTN', 'Search Keyword Update'],
        ];

        $this->repository->update([$update], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->getEntities()
            ->get($id);

        $customSearchKeywords = $product->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword Update', $customSearchKeywords);
    }

    /**
     * @param array<string> $searchKeyword
     */
    private function createProduct(string $id, array $searchKeyword): void
    {
        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test'],
            'tax' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test', 'taxRate' => 15],
            'customSearchKeywords' => $searchKeyword,
        ];

        $this->repository->create([$data], $this->context);
    }
}
