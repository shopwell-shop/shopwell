<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportExceptionImportRecordEvent::class)]
class ImportExportExceptionRecordTest extends TestCase
{
    private ImportExportExceptionImportRecordEvent $exceptionRecord;

    protected function setUp(): void
    {
        $exception = static::createStub(\Throwable::class);
        $context = Context::createDefaultContext();
        $config = static::createStub(Config::class);

        $this->exceptionRecord = new ImportExportExceptionImportRecordEvent(
            $exception,
            [],
            [],
            $config,
            $context
        );
    }

    public function testHasException(): void
    {
        static::assertTrue($this->exceptionRecord->hasException());
        static::assertInstanceOf(\Throwable::class, $this->exceptionRecord->getException());
    }

    public function testRemoveException(): void
    {
        $this->exceptionRecord->removeException();
        static::assertFalse($this->exceptionRecord->hasException());
        static::assertNull($this->exceptionRecord->getException());
    }

    public function testReplaceException(): void
    {
        $this->exceptionRecord->removeException();
        $newException = static::createStub(\Throwable::class);
        $this->exceptionRecord->setException($newException);
        static::assertTrue($this->exceptionRecord->hasException());
    }
}
