<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializerSubscriber;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(MediaSerializer::class)]
class MediaSerializerTest extends TestCase
{
    public function testExistingMediaWithSameHashDoesNotPersistDownloadedFileAgain(): void
    {
        $context = Context::createDefaultContext();
        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);
        $mediaFolderRepository = static::createStub(EntityRepository::class);
        $mediaRepository = $this->createMock(EntityRepository::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MediaSerializerSubscriber($mediaSerializer));

        $existingMediaId = Uuid::randomHex();
        $hash = 'existing-file-hash';

        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn(new MediaFile(
                '/tmp/foo/bar/baz',
                'image/png',
                'png',
                1337,
                $hash
            ));

        $mediaRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$existingMediaId], new Criteria(), $context));

        $fileSaver->expects($this->never())
            ->method('persistFileToMedia');

        $result = $mediaSerializer->deserialize(new Config([], [], []), $mediaDefinition, [
            'url' => 'http://172.16.11.80/shopware-logo.png',
            'mediaFolderId' => Uuid::randomHex(),
        ]);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertSame($existingMediaId, $result['id']);

        $writtenResult = new EntityWriteResult($existingMediaId, $result, MediaDefinition::ENTITY_NAME, 'insert');
        $writtenEvent = new EntityWrittenEvent(MediaDefinition::ENTITY_NAME, [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }

    public function testExistingMediaWithSameIdAndHashDoesNotPersistDownloadedFileAgain(): void
    {
        $context = Context::createDefaultContext();
        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);
        $mediaFolderRepository = static::createStub(EntityRepository::class);
        $mediaRepository = $this->createMock(EntityRepository::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MediaSerializerSubscriber($mediaSerializer));

        $existingMediaId = Uuid::randomHex();
        $hash = 'existing-file-hash';

        $mediaEntity = new MediaEntity();
        $mediaEntity->assign([
            'id' => $existingMediaId,
            'url' => 'http://shopware.test/media/generated/path/shopware-logo.png',
            'metaData' => [
                'hash' => $hash,
            ],
        ]);

        $mediaRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(MediaDefinition::ENTITY_NAME, 1, new MediaCollection([$mediaEntity]), null, new Criteria(), $context));

        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn(new MediaFile(
                '/tmp/foo/bar/baz',
                'image/png',
                'png',
                1337,
                $hash
            ));

        $mediaRepository->expects($this->never())
            ->method('searchIds');

        $fileSaver->expects($this->never())
            ->method('persistFileToMedia');

        $result = $mediaSerializer->deserialize(new Config([], [], []), $mediaDefinition, [
            'id' => $existingMediaId,
            'url' => 'http://shopware.test/media/exported/path/shopware-logo.png',
            'mediaFolderId' => Uuid::randomHex(),
        ]);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertSame($existingMediaId, $result['id']);

        $writtenResult = new EntityWriteResult($existingMediaId, $result, MediaDefinition::ENTITY_NAME, 'update');
        $writtenEvent = new EntityWrittenEvent(MediaDefinition::ENTITY_NAME, [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }
}
