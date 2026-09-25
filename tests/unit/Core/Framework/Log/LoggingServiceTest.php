<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Log;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopwell\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\FlowLogEvent;
use Shopwell\Core\Framework\Log\LoggingService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\Logging\Event\LogAwareTestFlowEvent;
use Shopwell\Core\Test\Stub\Doctrine\TestExceptionFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoggingService::class)]
class LoggingServiceTest extends TestCase
{
    protected Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
    }

    public function testWriteFlowEvents(): void
    {
        $handler = new TestHandler();
        $service = $this->getLoggingService($handler);

        $service->logFlowEvent(
            new FlowLogEvent(TestFlowBusinessEvent::EVENT_NAME, new TestFlowBusinessEvent($this->context))
        );

        $testRecord = $this->getRecord($handler);

        static::assertSame(TestFlowBusinessEvent::EVENT_NAME, $testRecord->message);
        static::assertSame('test', $testRecord->context['environment']);
        static::assertSame(Level::Debug, $testRecord->level);
        static::assertEmpty($testRecord->context['additionalData']);
    }

    public function testWriteMailSendLogEvents(): void
    {
        $handler = new TestHandler();
        $service = $this->getLoggingService($handler);

        $service->logFlowEvent(
            new FlowLogEvent(
                TestFlowBusinessEvent::EVENT_NAME,
                new MailErrorEvent($this->context, Level::Error, TestExceptionFactory::createException('test'))
            )
        );

        $testRecord = $this->getRecord($handler);

        static::assertSame(MailErrorEvent::NAME, $testRecord->message);
        static::assertSame('test', $testRecord->context['environment']);
        static::assertSame(Level::Error, $testRecord->level);
    }

    public function testWriteLogAwareFlowEvent(): void
    {
        $handler = new TestHandler();
        $service = $this->getLoggingService($handler);

        $service->logFlowEvent(
            new FlowLogEvent(LogAwareTestFlowEvent::EVENT_NAME, new LogAwareTestFlowEvent($this->context))
        );

        $testRecord = $this->getRecord($handler);

        static::assertSame(Level::Emergency, $testRecord->level);
        static::assertNotEmpty($testRecord->context['additionalData']);
        static::assertArrayHasKey('awesomekey', $testRecord->context['additionalData']);
        static::assertSame('awesomevalue', $testRecord->context['additionalData']['awesomekey']);
    }

    private function getLoggingService(TestHandler $handler): LoggingService
    {
        return new LoggingService('test', new Logger('testlogger', [$handler]));
    }

    private function getRecord(TestHandler $handler): LogRecord
    {
        $records = $handler->getRecords();
        static::assertCount(1, $records);

        return $records[0];
    }
}
