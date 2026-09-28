<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class StringFieldSerializerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = static::getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testLessThanSignThatDoesNotStartATagSurvivesTheWrite(): void
    {
        $id = $this->createProduct('I <3 Kisses');

        static::assertSame('I <3 Kisses', $this->readProductName($id));
    }

    public function testMarkupIsRemovedFromTheWrittenValue(): void
    {
        $id = $this->createProduct('<b>x</b>');

        static::assertSame('x', $this->readProductName($id));
    }

    private function createProduct(string $name): string
    {
        $id = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $id,
                'productNumber' => $id,
                'name' => $name,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ], $this->context);

        return $id;
    }

    private function readProductName(string $id): ?string
    {
        $product = $this->productRepository->search(new Criteria([$id]), $this->context)->getEntities()->first();

        static::assertNotNull($product);

        return $product->getName();
    }
}
