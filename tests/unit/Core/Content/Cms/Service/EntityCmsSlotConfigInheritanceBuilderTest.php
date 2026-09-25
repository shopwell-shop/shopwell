<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
#[CoversClass(EntityCmsSlotConfigInheritanceBuilder::class)]
class EntityCmsSlotConfigInheritanceBuilderTest extends TestCase
{
    public function testBuildMergesSlotConfigAlongExplicitParentLanguageChain(): void
    {
        $childLanguageId = Uuid::randomHex();
        $parentLanguageId = Uuid::randomHex();
        $grandParentLanguageId = Uuid::randomHex();

        $builder = new EntityCmsSlotConfigInheritanceBuilder(
            $this->createConnectionWithParentLanguageIds([
                $parentLanguageId,
                $grandParentLanguageId,
                null,
            ]),
        );

        $translations = new ProductTranslationCollection([
            $this->createTranslation($grandParentLanguageId, [
                'slot-a' => ['headline' => ['value' => 'grand-parent']],
            ]),
            $this->createTranslation($parentLanguageId, [
                'slot-a' => ['headline' => ['value' => 'parent']],
                'slot-b' => ['headline' => ['value' => 'parent']],
            ]),
            $this->createTranslation($childLanguageId, [
                'slot-b' => ['headline' => ['value' => 'child']],
            ]),
        ]);

        $result = $builder->build($translations, $this->createSalesChannelContext($childLanguageId));

        static::assertSame([
            'slot-a' => ['headline' => ['value' => 'parent']],
            'slot-b' => ['headline' => ['value' => 'child']],
        ], $result);
    }

    public function testBuildRetainsParentLanguageFieldsWhenChildOverridesPartialSlot(): void
    {
        $childLanguageId = Uuid::randomHex();
        $parentLanguageId = Uuid::randomHex();

        $builder = new EntityCmsSlotConfigInheritanceBuilder(
            $this->createConnectionWithParentLanguageIds([
                $parentLanguageId,
                null,
            ]),
        );

        $translations = new ProductTranslationCollection([
            $this->createTranslation($parentLanguageId, [
                'slot-a' => [
                    'headline' => ['value' => 'parent headline'],
                    'content' => ['value' => 'parent content'],
                ],
            ]),
            $this->createTranslation($childLanguageId, [
                'slot-a' => [
                    'content' => ['value' => 'child content'],
                ],
            ]),
        ]);

        $result = $builder->build($translations, $this->createSalesChannelContext($childLanguageId));

        static::assertSame([
            'slot-a' => [
                'headline' => ['value' => 'parent headline'],
                'content' => ['value' => 'child content'],
            ],
        ], $result);
    }

    public function testBuildDoesNotMergeSystemLanguageWithoutExplicitParent(): void
    {
        $childLanguageId = Uuid::randomHex();
        $systemLanguageId = Uuid::randomHex();

        $builder = new EntityCmsSlotConfigInheritanceBuilder(
            $this->createConnectionWithParentLanguageIds([null]),
        );

        $translations = new ProductTranslationCollection([
            $this->createTranslation($systemLanguageId, [
                'slot-a' => ['headline' => ['value' => 'system']],
            ]),
        ]);

        $result = $builder->build($translations, $this->createSalesChannelContext($childLanguageId));

        static::assertNull($result);
    }

    /**
     * @param array<string, array<string, mixed>> $slotConfig
     */
    private function createTranslation(string $languageId, array $slotConfig): ProductTranslationEntity
    {
        $translation = new ProductTranslationEntity();
        $translation->setUniqueIdentifier('translation-' . $languageId);
        $translation->setLanguageId($languageId);
        $translation->setSlotConfig($slotConfig);

        return $translation;
    }

    /**
     * @param list<?string> $parentLanguageIds
     */
    private function createConnectionWithParentLanguageIds(array $parentLanguageIds): Connection
    {
        $connection = static::createStub(Connection::class);
        $queryBuilder = static::createStub(QueryBuilder::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $results = array_map(function (?string $parentLanguageId): Result {
            $result = $this->createStub(Result::class);
            $result->method('fetchOne')->willReturn($parentLanguageId);

            return $result;
        }, $parentLanguageIds);

        $queryBuilder->method('executeQuery')->willReturnOnConsecutiveCalls(...$results);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }

    private function createSalesChannelContext(string $languageId): SalesChannelContext
    {
        return Generator::generateSalesChannelContext(new Context(
            new SalesChannelApiSource(Uuid::randomHex()),
            [],
            languageIdChain: [$languageId],
        ));
    }
}
