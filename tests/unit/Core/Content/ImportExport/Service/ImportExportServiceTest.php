<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopwell\Core\Content\ImportExport\ImportExportException;
use Shopwell\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopwell\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopwell\Core\Content\ImportExport\Service\FileService;
use Shopwell\Core\Content\ImportExport\Service\ImportExportService;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserDefinition;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportService::class)]
class ImportExportServiceTest extends TestCase
{
    public function testPrepareExportWithImportOnlyProfileThrowsException(): void
    {
        $profileId = Uuid::randomHex();

        $this->expectExceptionObject(ImportExportException::profileWrongType($profileId, 'import'));

        $this->createImportExportService($profileId)->prepareExport(
            Context::createDefaultContext(),
            $profileId,
            new \DateTimeImmutable(),
        );
    }

    public function testPrepareExportWithImportOnlyProfileDoesNotThrowExceptionIfInvalidRecordsShouldBeExported(): void
    {
        $profileId = Uuid::randomHex();

        $log = $this->createImportExportService($profileId)->prepareExport(
            Context::createDefaultContext(),
            $profileId,
            new \DateTimeImmutable(),
            activity: ImportExportLogEntity::ACTIVITY_INVALID_RECORDS_EXPORT
        );

        static::assertSame($profileId, $log->getProfileId());
        static::assertSame('test_profile', $log->getProfileName());
    }

    private function createImportExportService(string $profileId): ImportExportService
    {
        $profile = new ImportExportProfileEntity();
        $profile->setId($profileId);
        $profile->setUniqueIdentifier($profileId);
        $profile->setType(ImportExportProfileEntity::TYPE_IMPORT);
        $profile->setTechnicalName('test_profile');
        $profile->setConfig([]);
        $profile->setSourceEntity(ProductDefinition::ENTITY_NAME);
        $profile->setFileType('text/csv');

        $logRepo = new StaticEntityRepository([], new ImportExportLogDefinition());

        /** @var StaticEntityRepository<UserCollection> */
        $userRepo = new StaticEntityRepository([], new UserDefinition());

        $profileRepo = new StaticEntityRepository([new EntityCollection([$profile])], new ImportExportProfileDefinition());

        return new ImportExportService(
            $logRepo,
            $userRepo,
            $profileRepo,
            static::createStub(FileService::class),
        );
    }
}
