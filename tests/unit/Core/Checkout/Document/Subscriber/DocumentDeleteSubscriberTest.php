<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Document\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopwell\Core\Checkout\Document\DocumentException;
use Shopwell\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopwell\Core\Checkout\Document\Subscriber\DocumentDeleteSubscriber;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopwell\Core\Checkout\DocumentV2\DocumentCollection;
use Shopwell\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopwell\Core\Checkout\DocumentV2\DocumentEntity;
use Shopwell\Core\Checkout\DocumentV2\Event\DocumentDeletedEvent;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentDeleteSubscriber::class)]
class DocumentDeleteSubscriberTest extends TestCase
{
    public function testBeforeDeleteDeletesMediaFilesOnSuccess(): void
    {
        $documentId = Uuid::randomBytes();
        $mediaId = Uuid::randomHex();
        $mediaIdA11y = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();
        $documentNumber = '1000';

        $document = (new DocumentEntity())->assign([
            'id' => $documentId,
            'documentMediaFileId' => $mediaId,
            'documentA11yMediaFileId' => $mediaIdA11y,
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'documentNumber' => $documentNumber,
        ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $documentDefinition = new DocumentDefinition();
        $documentDefinition->compile($definitionInstanceRegistry);

        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([]), // dependency check with empty result
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria([$documentId]),
                Context::createDefaultContext(),
            ),
        ], $documentDefinition);

        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile($definitionInstanceRegistry);

        $mediaRepository = new StaticEntityRepository(
            [],
            $mediaDefinition,
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(DocumentDeletedEvent::class))
            ->willReturnCallback(static function (DocumentDeletedEvent $event) use ($documentId, $orderId, $orderVersionId, $documentNumber) {
                static::assertSame($documentId, $event->documentId);
                static::assertSame($orderId, $event->orderId);
                static::assertSame($orderVersionId, $event->orderVersionId);
                static::assertSame($documentNumber, $event->documentNumber);

                return $event;
            });

        $subscriber = new DocumentDeleteSubscriber(
            $documentRepository,
            $mediaRepository,
            $eventDispatcher,
        );

        $entityDeleteEvent = $this->createEntityDeleteEvent(
            $documentDefinition,
            $documentId
        );

        $subscriber->beforeDelete($entityDeleteEvent);
        $entityDeleteEvent->success();

        $deleted = $mediaRepository->deletes;
        static::assertCount(1, $deleted);
        static::assertCount(2, $deleted[0]);
        foreach ($deleted[0] as $mediaFile) {
            static::assertContains($mediaFile['id'], [$mediaId, $mediaIdA11y]);
        }
    }

    public function testBeforeDeleteDeletesDocumentV2FileMediaOnSuccess(): void
    {
        $documentId = Uuid::randomBytes();
        $pdfMediaId = Uuid::randomHex();
        $htmlMediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();
        $documentNumber = '1000';

        $pdfDocumentFile = (new DocumentFileEntity())->assign([
            'id' => Uuid::randomHex(),
            'documentId' => Uuid::fromBytesToHex($documentId),
            'documentFormat' => 'pdf',
            'mediaId' => $pdfMediaId,
        ]);
        $htmlDocumentFile = (new DocumentFileEntity())->assign([
            'id' => Uuid::randomHex(),
            'documentId' => Uuid::fromBytesToHex($documentId),
            'documentFormat' => 'html',
            'mediaId' => $htmlMediaId,
        ]);

        $document = (new DocumentEntity())->assign([
            'id' => $documentId,
            'documentFiles' => new DocumentFileCollection([$pdfDocumentFile, $htmlDocumentFile]),
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'documentNumber' => $documentNumber,
        ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $documentDefinition = new DocumentDefinition();
        $documentDefinition->compile($definitionInstanceRegistry);

        $documentRepository = new StaticEntityRepository([
            new DocumentCollection([]), // dependency check with empty result
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$document]),
                null,
                new Criteria([$documentId]),
                Context::createDefaultContext(),
            ),
        ], $documentDefinition);

        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile($definitionInstanceRegistry);

        $mediaRepository = new StaticEntityRepository(
            [],
            $mediaDefinition,
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(DocumentDeletedEvent::class))
            ->willReturnCallback(static function (DocumentDeletedEvent $event) use ($documentId, $orderId, $orderVersionId, $documentNumber) {
                static::assertSame($documentId, $event->documentId);
                static::assertSame($orderId, $event->orderId);
                static::assertSame($orderVersionId, $event->orderVersionId);
                static::assertSame($documentNumber, $event->documentNumber);

                return $event;
            });

        $subscriber = new DocumentDeleteSubscriber(
            $documentRepository,
            $mediaRepository,
            $eventDispatcher,
        );

        $entityDeleteEvent = $this->createEntityDeleteEvent(
            $documentDefinition,
            $documentId
        );

        $subscriber->beforeDelete($entityDeleteEvent);
        $entityDeleteEvent->success();

        $deleted = $mediaRepository->deletes;
        static::assertCount(1, $deleted);
        static::assertCount(2, $deleted[0]);
        foreach ($deleted[0] as $mediaFile) {
            static::assertContains($mediaFile['id'], [$pdfMediaId, $htmlMediaId]);
        }
    }

    public function testBeforeDeleteShouldThrowExceptionWhenDependenciesOnOtherDocumentsExists(): void
    {
        $documentId = Uuid::randomBytes();
        $dependingDocumentId = Uuid::randomBytes();
        $dependingDocumentNumber = '10001';

        $documentType = (new DocumentTypeEntity())->assign([
            'id' => Uuid::randomBytes(),
            'technicalName' => CreditNoteRenderer::TYPE,
        ]);

        $dependingDocument = (new DocumentEntity())->assign([
            'id' => $dependingDocumentId,
            'referencedDocumentId' => $documentId,
            'documentNumber' => $dependingDocumentNumber,
            'documentType' => $documentType,
        ]);

        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $documentDefinition = new DocumentDefinition();
        $documentDefinition->compile($definitionInstanceRegistry);

        $documentRepository = new StaticEntityRepository([
            new EntitySearchResult(
                DocumentDefinition::ENTITY_NAME,
                1,
                new DocumentCollection([$dependingDocument]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ], $documentDefinition);

        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile($definitionInstanceRegistry);

        $mediaRepository = new StaticEntityRepository(
            [],
            $mediaDefinition,
        );

        $subscriber = new DocumentDeleteSubscriber(
            $documentRepository,
            $mediaRepository,
            static::createStub(EventDispatcherInterface::class),
        );

        $entityDeleteEvent = $this->createEntityDeleteEvent(
            $documentDefinition,
            $documentId
        );

        $this->expectExceptionObject(DocumentException::documentHasDependentDocuments(
            [
                \sprintf(
                    '%s %s (%s)',
                    CreditNoteRenderer::TYPE,
                    $dependingDocumentNumber,
                    $dependingDocumentId,
                ),
            ]
        ));
        $subscriber->beforeDelete($entityDeleteEvent);
    }

    private function createEntityDeleteEvent(
        DocumentDefinition $documentDefinition,
        string $documentId
    ): EntityDeleteEvent {
        return EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $documentDefinition,
                    ['id' => $documentId],
                    new EntityExistence(
                        DocumentDefinition::ENTITY_NAME,
                        ['id' => $documentId],
                        true,
                        false,
                        false,
                        [
                            'exists' => true,
                            'id' => $documentId,
                        ],
                    )
                ),
            ]
        );
    }
}
