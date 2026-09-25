<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopwell\Core\Content\ImportExport\ImportExportException;
use Shopwell\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Feature\FeatureException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportExceptionImportExportHandlerEvent::class)]
class ImportExportExceptionImportExportHandlerEventTest extends TestCase
{
    public function testClearExceptionRemovesException(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(
            new \RuntimeException('test'),
            $message,
            Context::createDefaultContext()
        );

        static::assertSame($message, $event->getMessage());

        $event->clearException();

        static::assertFalse($event->hasException());
        static::assertNull($event->getException());
    }

    public function testSetExceptionReplacesException(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(
            new \RuntimeException('test'),
            $message,
            Context::createDefaultContext()
        );

        $newException = new \LogicException('new');
        $event->setException($newException);

        static::assertTrue($event->hasException());
        static::assertSame($newException, $event->getException());
    }

    public function testSetExceptionWithNullClearsException(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(
            new \RuntimeException('test'),
            $message,
            Context::createDefaultContext()
        );

        $event->setException(null);

        static::assertFalse($event->hasException());
        static::assertNull($event->getException());
    }

    public function testGetContextReturnsPassedContext(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $context = Context::createDefaultContext();
        $event = new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message, $context);

        static::assertSame($context, $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsContextWhenFeatureInactiveAndContextProvided(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $context = Context::createDefaultContext();
        $event = new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message, $context);

        static::assertSame($context, $event->getNullableContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);

        static::assertNull($event->getNullableContext());
    }

    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $context to ' . ImportExportExceptionImportExportHandlerEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetContextThrowsWithoutContext(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);

        $this->expectExceptionObject(ImportExportException::invalidEventData('No context provided. Pass $context to the constructor of ' . ImportExportExceptionImportExportHandlerEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextThrowsWhenFeatureActive(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message, Context::createDefaultContext());

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: getNullableContext() is deprecated, use getContext() instead.'));
        $event->getNullableContext();
    }
}
