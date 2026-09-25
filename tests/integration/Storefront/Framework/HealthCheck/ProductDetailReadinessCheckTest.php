<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\SystemCheck\Check\Status;
use Shopwell\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;

/**
 * @internal
 */
#[Package('discovery')]
class ProductDetailReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->ids = new IdsCollection();

        $this->createSalesChannels();
    }

    public function testAllChecksAreHealthy(): void
    {
        $this->createProducts();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testCheckWithoutProducts(): void
    {
        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
    }

    private function createCheck(): ProductDetailReadinessCheck
    {
        return $this->getContainer()->get(ProductDetailReadinessCheck::class);
    }

    private function createSalesChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM `sales_channel_domain`');
        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel-1'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://shop.test',
                ],
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createProducts(): array
    {
        $salesChannelIds = [
            $this->ids->get('sales-channel-1'),
            $this->ids->get('sales-channel-2'),
        ];

        $products = [];
        foreach ($salesChannelIds as $index => $id) {
            $products[] = (new ProductBuilder($this->ids, 'product-' . $index))
                ->name('Test-' . $index)
                ->price(10)
                ->manufacturer('manufacturer')
                ->tax('tax')
                ->visibility($id)
                ->build();
        }

        $this->productRepository->create($products, Context::createDefaultContext());

        return $products;
    }
}
