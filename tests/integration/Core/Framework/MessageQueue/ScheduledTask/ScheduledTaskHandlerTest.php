<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\MessageQueue\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\MessageQueue\fixtures\DummyScheduledTaskHandler;
use Shopwell\Tests\Integration\Core\Framework\MessageQueue\fixtures\TestRescheduleOnFailureTask;
use Shopwell\Tests\Integration\Core\Framework\MessageQueue\fixtures\TestTask;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class ScheduledTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<ScheduledTaskCollection>
     */
    private EntityRepository $scheduledTaskRepo;

    private LoggerInterface&Stub $logger;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->scheduledTaskRepo = static::getContainer()->get('scheduled_task.repository');
        $this->logger = static::createStub(LoggerInterface::class);
    }

    #[DataProvider('allowedStatus')]
    public function testHandle(string $status): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $interval = 300;

        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => $interval,
                'defaultRunInterval' => $interval,
                'status' => $status,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = $this->createHandler($taskId);
        $handler($task);

        static::assertTrue($handler->wasCalled());

        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->getEntities()->get($taskId);

        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        $newOriginalNextExecution = clone $originalNextExecution;
        $newOriginalNextExecution->modify(\sprintf('+%d seconds', $interval));
        $newOriginalNextExecutionString = $newOriginalNextExecution->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $nextExecutionTimeString = $task->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
        static::assertSame($newOriginalNextExecutionString, $nextExecutionTimeString);
        static::assertNotSame($originalNextExecution->format(\DATE_ATOM), $task->getNextExecutionTime()->format(\DATE_ATOM));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function allowedStatus(): array
    {
        return [
            [ScheduledTaskDefinition::STATUS_RUNNING],
            [ScheduledTaskDefinition::STATUS_QUEUED],
            [ScheduledTaskDefinition::STATUS_FAILED],
        ];
    }

    public function testHandleWhenNewNextExecutionTimeLessThanNowTime(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-24 hours');
        $interval = 60;

        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => $interval,
                'defaultRunInterval' => $interval,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = $this->createHandler($taskId);
        $handler($task);
        $nowTime = new \DateTime();

        static::assertTrue($handler->wasCalled());

        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->getEntities()->get($taskId);

        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
        static::assertGreaterThanOrEqual(
            $task->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $nowTime->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
        static::assertNotSame($originalNextExecution->format(\DATE_ATOM), $task->getNextExecutionTime()->format(\DATE_ATOM));
    }

    public function testHandleOnException(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = $this->createHandler($taskId, true);

        $exception = null;

        try {
            $handler($task);
        } catch (\Exception $exception) {
        }

        static::assertInstanceOf(\RuntimeException::class, $exception);
        static::assertSame('This Exception should be thrown', $exception->getMessage());

        static::assertTrue($handler->wasCalled());

        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->getEntities()->get($taskId);
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertSame(ScheduledTaskDefinition::STATUS_FAILED, $task->getStatus());
    }

    public function testHandleOnExceptionWithRescheduleOnFailure(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestRescheduleOnFailureTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new TestRescheduleOnFailureTask();
        $task->setTaskId($taskId);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $handler = $this->createHandler($taskId, true, $logger);

        try {
            $handler($task);
        } catch (\Exception $exception) {
        }

        static::assertTrue($handler->wasCalled());

        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->getEntities()->get($taskId);
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
    }

    public function testHandleIgnoresIfTaskIsNotFound(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = $this->createHandler($taskId);
        $handler($task);

        static::assertFalse($handler->wasCalled());
    }

    #[DataProvider('notAllowedStatus')]
    public function testHandleIgnoresWhenTaskIsNotAllowedForExecution(string $status): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => $status,
                'nextExecutionTime' => new \DateTime(),
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = $this->createHandler($taskId);
        $handler($task);

        static::assertFalse($handler->wasCalled());

        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->getEntities()->get($taskId);
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertSame($status, $task->getStatus());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function notAllowedStatus(): array
    {
        return [
            [ScheduledTaskDefinition::STATUS_SCHEDULED],
            [ScheduledTaskDefinition::STATUS_INACTIVE],
        ];
    }

    private function createHandler(string $taskId, bool $shouldThrowException = false, ?LoggerInterface $logger = null): DummyScheduledTaskHandler
    {
        $logger ??= $this->logger;

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $logger, $taskId, $shouldThrowException);
        $handler->setScheduledTaskExecutor(new ScheduledTaskExecutor($this->scheduledTaskRepo, $logger, new NativeClock()));

        return $handler;
    }
}
