<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopwell\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DeleteExpiredFilesService::class)]
class DeleteExpiredFilesServiceTest extends TestCase
{
    public function testCountFilesUsesExpiredDateCutoffAndExactCount(): void
    {
        $now = new \DateTimeImmutable('2026-01-31T12:00:00+00:00');

        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $repository */
        $repository = StaticEntityRepository::of(EntityCollection::class, [
            static function (Criteria $criteria, Context $context) use ($now): IdSearchResult {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(RangeFilter::class, $filters[0]);
                static::assertSame('expireDate', $filters[0]->getField());
                static::assertSame(
                    $now->modify('-30 days')->format(\DATE_ATOM),
                    $filters[0]->getParameter(RangeFilter::LT)
                );

                return IdSearchResult::fromIds([], $criteria, $context, 4);
            },
        ]);

        static::assertSame(4, (new DeleteExpiredFilesService($repository, new MockClock($now)))->countFiles(Context::createDefaultContext()));
    }

    public function testDeleteFilesDeletesExpiredFileIds(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $repository */
        $repository = StaticEntityRepository::of(EntityCollection::class, [$ids]);

        (new DeleteExpiredFilesService($repository, new MockClock('2026-01-31 12:00:00')))->deleteFiles(Context::createDefaultContext());

        static::assertSame([['id' => $ids[0]], ['id' => $ids[1]]], $repository->deletes[0]);
    }

    public function testDeleteFilesDeletesNothingWhenNoExpiredFilesFound(): void
    {
        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $repository */
        $repository = StaticEntityRepository::of(EntityCollection::class, [[]]);

        (new DeleteExpiredFilesService($repository, new MockClock('2026-01-31 12:00:00')))->deleteFiles(Context::createDefaultContext());

        static::assertSame([[]], $repository->deletes);
    }
}
