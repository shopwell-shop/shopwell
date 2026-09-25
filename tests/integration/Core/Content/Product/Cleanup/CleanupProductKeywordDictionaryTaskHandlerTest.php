<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTaskHandler;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class CleanupProductKeywordDictionaryTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupProductKeywordDictionaryTaskHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = static::getContainer()->get(CleanupProductKeywordDictionaryTaskHandler::class);
    }

    public function testCleanup(): void
    {
        $now = new \DateTimeImmutable();
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $product = (new ProductBuilder($ids, 'test'))->price(100)->build();

        static::getContainer()->get('product.repository')->create([$product], $context);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product_keyword_dictionary');

        $productId = Uuid::fromHexToBytes($product['id']);
        $this->createProductSearchKeyword('test 1', $productId, $now, $context);
        $this->createProductSearchKeyword('test 2', $productId, $now, $context);

        $this->createProductKeywordDictionary('test 1');
        $this->createProductKeywordDictionary('test 2');
        $this->createProductKeywordDictionary('test 3');
        $this->createProductKeywordDictionary('test 4');

        $this->handler->run();

        $keywordDictionaries = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT keyword FROM product_keyword_dictionary');

        static::assertCount(2, $keywordDictionaries);
        static::assertContains('test 1', $keywordDictionaries);
        static::assertContains('test 2', $keywordDictionaries);
    }

    private function createProductSearchKeyword(string $keyword, string $productId, \DateTimeImmutable $date, Context $context): void
    {
        $searchKeyword = [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::randomBytes(),
            'product_version_id' => Uuid::fromHexToBytes($context->getVersionId()),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'product_id' => $productId,
            'keyword' => $keyword,
            'ranking' => 100,
            'created_at' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        static::getContainer()->get(Connection::class)->insert('product_search_keyword', $searchKeyword);
    }

    private function createProductKeywordDictionary(string $keyword): void
    {
        $searchKeyword = [
            'id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'keyword' => $keyword,
        ];

        static::getContainer()->get(Connection::class)->insert('product_keyword_dictionary', $searchKeyword);
    }
}
