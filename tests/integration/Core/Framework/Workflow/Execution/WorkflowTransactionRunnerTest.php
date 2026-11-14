<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\RetryableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowTransactionRunnerTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private WorkflowEventQueue $events;

    private WorkflowTransactionRunner $runner;

    protected function setUp(): void
    {
        // A separate connection exercises the outermost retry boundary, independent
        // of the integration suite's usual rollback transaction.
        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);
        $params = $connection->getParams();
        $params['wrapperClass'] = FailingCommitConnection::class;
        $this->connection = DriverManager::getConnection($params);
        $this->connection->executeStatement('CREATE TEMPORARY TABLE flow_event_retry_test (id INT NOT NULL) ENGINE=InnoDB');
        $this->events = new WorkflowEventQueue(new EventDispatcher());
        $this->runner = new WorkflowTransactionRunner($this->connection, $this->events);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function failurePoints(): iterable
    {
        yield 'callback failure' => [false];
        yield 'commit failure after callback returned' => [true];
    }

    #[DataProvider('failurePoints')]
    public function testRetryDiscardsRolledBackEventsAndWrites(bool $failCommit): void
    {
        $priorEvent = new \stdClass();
        $this->events->push($priorEvent);
        static::assertInstanceOf(FailingCommitConnection::class, $this->connection);
        $this->connection->failNextCommit = $failCommit;
        $attempt = 0;
        $successfulEvent = null;
        $this->runner->run(function () use (&$attempt, $failCommit, &$successfulEvent): void {
            ++$attempt;
            $event = (object) ['attempt' => $attempt];
            $this->events->push($event);
            $this->connection->insert('flow_event_retry_test', ['id' => $attempt]);
            if ($attempt === 1 && !$failCommit) {
                throw new RetryableFlowFailure('Retry this attempt');
            }

            $successfulEvent = $event;
        });

        static::assertSame(2, $attempt);
        static::assertSame([2], array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM flow_event_retry_test')));
        static::assertSame([$priorEvent, $successfulEvent], $this->events->pending());
        $this->events->flush();
        static::assertTrue($this->events->isEmpty());
    }

    public function testFailedTransactionCannotLeakEventsIntoNextSuccessfulOperation(): void
    {
        $failure = new \RuntimeException('Approval cannot advance');
        try {
            $this->runner->run(function () use ($failure): void {
                $this->connection->insert('flow_event_retry_test', ['id' => 1]);
                $this->events->push(new \stdClass());

                throw $failure;
            });
        } catch (\RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }

        static::assertSame([], $this->connection->fetchFirstColumn('SELECT id FROM flow_event_retry_test'));
        static::assertTrue($this->events->isEmpty());
        $nextEvent = new \stdClass();
        $this->runner->run(fn () => $this->events->push($nextEvent));
        static::assertSame([$nextEvent], $this->events->pending());
    }

    public function testDeferredEventsFromACommitRetryAreDispatchedOnlyOnceAfterCommit(): void
    {
        $dispatcher = new EventDispatcher();
        $events = new WorkflowEventQueue($dispatcher);
        $runner = new WorkflowTransactionRunner($this->connection, $events);
        $observed = [];
        $dispatcher->addListener(\stdClass::class, function (\stdClass $event) use (&$observed): void {
            $observed[] = [$event->attempt, $this->connection->getTransactionNestingLevel()];
        });
        static::assertInstanceOf(FailingCommitConnection::class, $this->connection);
        $this->connection->failNextCommit = true;
        $attempt = 0;

        $events->defer(function () use ($runner, $events, &$attempt, &$observed): void {
            $runner->run(function () use ($events, &$attempt, &$observed): void {
                ++$attempt;
                $events->push((object) ['attempt' => $attempt]);
                $this->connection->insert('flow_event_retry_test', ['id' => $attempt]);
                $events->flush();
                static::assertSame([], $observed);
            });
        });

        static::assertSame(2, $attempt);
        static::assertSame([[2, 0]], $observed);
        static::assertFalse($runner->isRunning());
        static::assertTrue($events->isEmpty());
        static::assertSame([2], array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM flow_event_retry_test')));
    }
}

/**
 * @internal
 */
class FailingCommitConnection extends Connection
{
    public bool $failNextCommit = false;

    public function commit(): void
    {
        if ($this->failNextCommit) {
            $this->failNextCommit = false;

            throw new RetryableFlowFailure('Commit must be retried');
        }

        parent::commit();
    }
}

/**
 * @internal
 */
class RetryableFlowFailure extends \RuntimeException implements Exception, RetryableException
{
}
