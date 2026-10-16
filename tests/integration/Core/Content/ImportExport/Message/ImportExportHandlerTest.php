<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\ImportExport\Message;

use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopwell\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopwell\Core\Content\ImportExport\ImportExportFactory;
use Shopwell\Core\Content\ImportExport\Message\ImportExportHandler;
use Shopwell\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopwell\Core\Content\ImportExport\Service\ImportExportService;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Content\ImportExport\AbstractImportExportTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportHandlerTest extends AbstractImportExportTestCase
{
    public function testImportExportHandlerDispatchesMessage(): void
    {
        $messageBus = static::getContainer()->get('messenger.default_bus');
        static::assertInstanceOf(TraceableMessageBus::class, $messageBus);

        $importExportMessageCount = \count(
            \array_filter($messageBus->getDispatchedMessages(), static function ($message): bool {
                return $message['message'] instanceof ImportExportMessage;
            })
        );

        $factory = static::getContainer()->get(ImportExportFactory::class);

        $context = Context::createDefaultContext();

        $importExportHandler = new ImportExportHandler($messageBus, $factory, $this->listener);

        $importExportService = static::getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(PropertyGroupOptionDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/../fixtures/properties.csv', 'properties.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $importExportMessage = new ImportExportMessage($context, $logEntity->getId(), ImportExportLogEntity::ACTIVITY_IMPORT);

        $importExportHandler->__invoke($importExportMessage);

        $messages = \array_filter($messageBus->getDispatchedMessages(), static function ($message): bool {
            return $message['message'] instanceof ImportExportMessage;
        });

        static::assertCount($importExportMessageCount + 1, $messages);

        $importExportMessage = $messages[\array_key_last($messages)]['message'];
        static::assertInstanceOf(ImportExportMessage::class, $importExportMessage);

        static::assertSame($logEntity->getId(), $importExportMessage->getLogId());
        static::assertSame($logEntity->getActivity(), $importExportMessage->getActivity());

        $updatedLogEntity = $this->getLogEntity($logEntity->getId());
        static::assertSame(50, $updatedLogEntity->getRecords());

        $importExportHandler->__invoke($importExportMessage);
        $updatedLogEntity = $this->getLogEntity($logEntity->getId());
        static::assertSame(100, $updatedLogEntity->getRecords());
    }

    public function testImportExportHandlerOnError(): void
    {
        $messageBus = static::getContainer()->get('messenger.default_bus');
        static::assertInstanceOf(TraceableMessageBus::class, $messageBus);

        $importExportMessageCount
            = \count(
                \array_filter($messageBus->getDispatchedMessages(), static function ($message): bool {
                    return $message['message'] instanceof ImportExportMessage;
                })
            );

        $factory = static::getContainer()->get(ImportExportFactory::class);
        $context = Context::createDefaultContext();

        $importExportHandler = new ImportExportHandler($messageBus, $factory, $this->listener);
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $profileId = $this->getDefaultProfileId(PropertyGroupOptionDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/../fixtures/properties.csv', 'properties.csv', 'text/csv');

        $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $importExportMessage = new ImportExportMessage($context, 'invalid_id', ImportExportLogEntity::ACTIVITY_IMPORT);

        $events = [];

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            ImportExportExceptionImportExportHandlerEvent::class,
            static function (ImportExportExceptionImportExportHandlerEvent $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $importExportHandler->__invoke($importExportMessage);

        $messages = \array_filter($messageBus->getDispatchedMessages(), static function ($message): bool {
            return $message['message'] instanceof ImportExportMessage;
        });

        static::assertCount($importExportMessageCount, $messages);
        static::assertCount(1, $events);
        static::assertInstanceOf(InvalidUuidException::class, $events[0]->getException());
        static::assertSame(
            0,
            $events[0]->getException()->getCode()
        );
        static::assertSame($importExportMessage, $events[0]->getMessage());
    }
}
