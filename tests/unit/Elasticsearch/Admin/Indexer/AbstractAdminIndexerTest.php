<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Admin\Indexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Doctrine\FakeConnection;
use Shopwell\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AbstractAdminIndexer::class)]
class AbstractAdminIndexerTest extends TestCase
{
    public function testGetSupportedSearchFieldsHandlesNestedProperties(): void
    {
        $languageId = Uuid::randomHex();

        $indexer = new class($languageId) extends AbstractAdminIndexer {
            public function __construct(private readonly string $languageId)
            {
            }

            public function getDecorated(): AbstractAdminIndexer
            {
                throw new DecorationPatternException(self::class);
            }

            public function getName(): string
            {
                return 'test-indexer';
            }

            public function getEntity(): string
            {
                return 'test_entity';
            }

            public function getIterator(): IterableQuery
            {
                return new LastIdQuery(new QueryBuilder(new FakeConnection([])));
            }

            public function fetch(array $ids): array
            {
                return [];
            }

            public function globalData(array $result, Context $context): array
            {
                return [
                    'total' => 0,
                    'data' => new EntityCollection([]),
                ];
            }

            public function mapping(array $mapping): array
            {
                return [
                    'properties' => [
                        'mediaFolder' => [
                            'properties' => [
                                'defaultFolder' => [
                                    'properties' => [
                                        'entity' => ['type' => 'keyword'],
                                    ],
                                ],
                            ],
                        ],
                        'title' => [
                            'properties' => [
                                $this->languageId => ['type' => 'keyword'],
                            ],
                        ],
                    ],
                ];
            }
        };

        $fields = $indexer->getSupportedSearchFields();

        static::assertContains('mediaFolder.defaultFolder.entity', $fields);
        static::assertContains('test_entity.mediaFolder.defaultFolder.entity', $fields);
        static::assertContains('title', $fields);
        static::assertContains('test_entity.title', $fields);
    }
}
