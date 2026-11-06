<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\RuleLoader;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderService;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopwell\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopwell\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopwell\Core\Content\Flow\Dispatching\FlowState;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopwell\Core\Content\Flow\Dispatching\TransactionalAction;
use Shopwell\Core\Content\Flow\FlowException;
use Shopwell\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Shopwell\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Shopwell\Core\Content\Product\Stock\AbstractStockStorage;
use Shopwell\Core\Content\Product\Stock\OrderStockSubscriber;
use Shopwell\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\WriteCommandExceptionEvent;
use Shopwell\Core\Framework\Event\OrderAware;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopwell\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('after-sales')]
class FlowExecutorTransactionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Context $context;

    private FlowExecutor $executor;

    private EventHookDispatcher $hooks;

    private string $orderId;

    private string $transactionId;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->executor = $container->get(FlowExecutor::class);
        $this->hooks = EventHookDispatcher::fromContainer($container);

        // The Flow action must own the outermost transaction for contention retries to run.
        static::assertSame(0, $this->connection->getTransactionNestingLevel());
        $ids = new IdsCollection();
        $orderNumber = Uuid::randomHex();
        $this->orderId = $ids->get($orderNumber);
        $this->transactionId = $ids->get('payment');
        $container->get('order.repository')->create([
            (new OrderBuilder($ids, $orderNumber))->addTransaction('payment')->build(),
        ], $this->context);
    }

    protected function tearDown(): void
    {
        try {
            static::assertSame(0, $this->connection->getTransactionNestingLevel());
        } finally {
            $this->connection->delete('state_machine_history', ['referenced_id' => Uuid::fromHexToBytes($this->orderId)]);
            $this->connection->delete('state_machine_history', ['referenced_id' => Uuid::fromHexToBytes($this->transactionId)]);
            $this->connection->delete('`order`', ['id' => Uuid::fromHexToBytes($this->orderId)]);
        }
    }

    public function testStateActionRetriesWriteContentionAndCompensatesEachFailedAttempt(): void
    {
        $attempts = 0;
        $successCalls = 0;
        $errorCalls = 0;
        $this->hooks->on(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$successCalls, &$errorCalls): void {
            if ($event->getCommandsForEntity('order') === []) {
                return;
            }

            $event->addSuccess(static function () use (&$successCalls): void {
                ++$successCalls;
            });
            $event->addError(static function () use (&$errorCalls): void {
                ++$errorCalls;
            });
        });
        $this->hooks->on(PostWriteValidationEvent::class, static function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw self::recordChangedException();
            }
        });

        $this->executeStateAction(['order' => 'cancelled']);

        static::assertSame(2, $attempts);
        static::assertSame(1, $successCalls);
        static::assertSame(1, $errorCalls);
        static::assertSame('cancelled', $this->readOrderState());
        static::assertSame(1, $this->countHistory());
    }

    public function testLaterTransitionFailureRollsBackAllStatesWithoutReplayingEarlierCallbacks(): void
    {
        $transitionCalls = 0;
        $paymentAttempts = 0;
        $failure = self::recordChangedException();
        $this->hooks->on(StateMachineTransitionEvent::class, static function () use (&$transitionCalls): void {
            ++$transitionCalls;
        });
        $this->hooks->on(PostWriteValidationEvent::class, static function (PostWriteValidationEvent $event) use (&$paymentAttempts, $failure): void {
            foreach ($event->getCommands() as $command) {
                if ($command->getEntityName() === 'order_transaction') {
                    ++$paymentAttempts;

                    throw $failure;
                }
            }
        });

        $this->expectExceptionObject(FlowException::transactionFailed($failure));

        try {
            $this->executeStateAction(['order' => 'cancelled', 'order_transaction' => 'cancelled']);
        } finally {
            static::assertSame(1, $transitionCalls);
            static::assertSame(1, $paymentAttempts);
            static::assertSame('open', $this->readOrderState());
            static::assertSame('open', $this->connection->fetchOne(
                'SELECT state.technical_name FROM order_transaction transaction INNER JOIN state_machine_state state ON state.id = transaction.state_id WHERE transaction.id = :id',
                ['id' => Uuid::fromHexToBytes($this->transactionId)]
            ));
            static::assertSame(0, $this->countHistory());
        }
    }

    public function testStateActionRetriesARealLockWaitTimeoutFromAnotherConnection(): void
    {
        $blocker = DriverManager::getConnection($this->connection->getParams());
        $originalTimeout = (int) $this->connection->fetchOne('SELECT @@SESSION.innodb_lock_wait_timeout');
        $attempts = 0;
        $successCalls = 0;
        $errorCalls = 0;
        $contentionErrors = [];

        $this->hooks->on(EntityWriteEvent::class, static function (EntityWriteEvent $event) use ($blocker, &$attempts, &$successCalls, &$errorCalls): void {
            if ($event->getCommandsForEntity('order') === []) {
                return;
            }

            ++$attempts;
            $event->addSuccess(static function () use (&$successCalls): void {
                ++$successCalls;
            });
            $event->addError(static function () use ($blocker, &$errorCalls): void {
                ++$errorCalls;
                $blocker->rollBack();
            });
        });
        $this->hooks->on(WriteCommandExceptionEvent::class, static function (WriteCommandExceptionEvent $event) use (&$contentionErrors): void {
            $contentionErrors[] = $event->getException();
        });

        try {
            $this->connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            $blocker->beginTransaction();
            static::assertSame(Uuid::fromHexToBytes($this->orderId), $blocker->fetchOne(
                'SELECT id FROM `order` WHERE id = :id AND version_id = :version FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($this->orderId), 'version' => Uuid::fromHexToBytes($this->context->getVersionId())]
            ));

            $this->executeStateAction(['order' => 'cancelled']);
        } finally {
            if ($blocker->isTransactionActive()) {
                $blocker->rollBack();
            }
            $blocker->close();
            $this->connection->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . $originalTimeout);
        }

        static::assertSame(2, $attempts);
        static::assertCount(1, $contentionErrors);
        static::assertInstanceOf(LockWaitTimeoutException::class, $contentionErrors[0]);
        static::assertSame(1205, $contentionErrors[0]->getCode());
        static::assertSame(1, $errorCalls);
        static::assertSame(1, $successCalls);
        static::assertSame('cancelled', $this->readOrderState());
        static::assertSame(1, $this->countHistory());
    }

    public function testNonLiveCoreStateActionDoesNotRetry(): void
    {
        $failure = self::recordChangedException();
        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())->method('orderStateTransition')->willThrowException($failure);
        $action = new SetOrderStateAction($this->connection, $orderService);
        $this->executor = $this->createExecutor($action);
        $this->context = $this->context->createWithVersionId(Uuid::randomHex());

        $this->expectExceptionObject(FlowException::transactionFailed($failure));

        $this->executeStateAction(['order' => 'cancelled']);
    }

    public function testOverriddenStateActionDoesNotOptIntoRetries(): void
    {
        $failure = self::recordChangedException();
        $action = $this->getMockBuilder(SetOrderStateAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['handleFlow'])
            ->getMock();
        $action->expects($this->once())->method('handleFlow')->willThrowException($failure);
        $this->executor = $this->createExecutor($action);

        $this->expectExceptionObject(FlowException::transactionFailed($failure));

        $this->executeStateAction(['order' => 'cancelled']);
    }

    public function testOtherTransactionalActionsDoNotOptIntoRetries(): void
    {
        $failure = self::recordChangedException();
        $action = new FailingTransactionalFlowAction($failure);
        $executor = $this->createExecutor($action);
        $sequence = new ActionSequence();
        $sequence->action = $action::getName();
        $flow = new StorableFlow('test.order.transition', $this->context);
        $flow->setFlowState(new FlowState());

        $this->expectExceptionObject(FlowException::transactionFailed($failure));

        try {
            $executor->executeAction($sequence, $flow);
        } finally {
            static::assertSame(1, $action->calls);
        }
    }

    public function testStockFailureDoesNotReplayCompletedWriteCallbacks(): void
    {
        $failure = self::recordChangedException();
        $successCalls = 0;
        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())->method('alter')->willThrowException($failure);
        $subscriber = new OrderStockSubscriber($this->connection, $stockStorage, enableStockManagement: true);
        $this->hooks->on(StateMachineTransitionEvent::class, $subscriber->stateChanged(...));
        $this->hooks->on(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$successCalls): void {
            $event->addSuccess(static function () use (&$successCalls): void {
                ++$successCalls;
            });
        });

        $this->expectExceptionObject(FlowException::transactionFailed($failure));

        try {
            $this->executeStateAction(['order' => 'cancelled']);
        } finally {
            static::assertSame(1, $successCalls);
            static::assertSame('open', $this->readOrderState());
            static::assertSame(0, $this->countHistory());
        }
    }

    public function testCallbacksFromAWriteUsingAnotherContextAlsoPreventReplay(): void
    {
        $tagId = Uuid::randomHex();
        $attempts = 0;
        $failure = self::recordChangedException();
        $tagRepository = static::getContainer()->get('tag.repository');
        $this->hooks->on(EntityWriteEvent::class, static function (EntityWriteEvent $event) use ($tagId, $tagRepository, &$attempts): void {
            if ($event->getCommandsForEntity('order') === []) {
                return;
            }

            ++$attempts;
            $tagRepository->create([['id' => $tagId, 'name' => $tagId]], Context::createDefaultContext());
        });
        $this->hooks->on(PostWriteValidationEvent::class, static function (PostWriteValidationEvent $event) use ($failure): void {
            foreach ($event->getCommands() as $command) {
                if ($command->getEntityName() === 'order') {
                    throw $failure;
                }
            }
        });

        $this->expectExceptionObject(FlowException::transactionFailed($failure));

        try {
            $this->executeStateAction(['order' => 'cancelled']);
        } finally {
            $remainingTags = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tag WHERE id = :id', ['id' => Uuid::fromHexToBytes($tagId)]);
            $this->connection->delete('tag', ['id' => Uuid::fromHexToBytes($tagId)]);
            static::assertSame(1, $attempts);
            static::assertSame('open', $this->readOrderState());
            static::assertSame(0, $this->countHistory());
            static::assertSame(0, $remainingTags);
        }
    }

    private function createExecutor(FlowAction $action): FlowExecutor
    {
        $container = static::getContainer();

        return new FlowExecutor(
            $container->get('event_dispatcher'),
            $container->get(AppFlowActionProvider::class),
            $container->get(RuleLoader::class),
            $container->get(FlowRuleScopeBuilder::class),
            $this->connection,
            $container->get(ExtensionDispatcher::class),
            $container->get('logger'),
            [$action::getName() => $action],
            $container->get(FlowMetricsInstrumentor::class),
        );
    }

    /**
     * @param array<string, string> $config
     */
    private function executeStateAction(array $config): void
    {
        $sequence = new ActionSequence();
        $sequence->action = SetOrderStateAction::getName();
        $sequence->config = $config;
        $flow = new StorableFlow('test.order.transition', $this->context, data: [OrderAware::ORDER_ID => $this->orderId]);
        $flow->setFlowState(new FlowState());

        $this->executor->executeAction($sequence, $flow);
    }

    private static function recordChangedException(): DriverException
    {
        return new DriverException(new PdoException('Record has changed since last read', 'HY000', 1020), null);
    }

    private function readOrderState(): string
    {
        return $this->connection->fetchOne(
            'SELECT state.technical_name FROM `order` INNER JOIN state_machine_state state ON state.id = `order`.state_id WHERE `order`.id = :id',
            ['id' => Uuid::fromHexToBytes($this->orderId)]
        );
    }

    private function countHistory(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM state_machine_history WHERE referenced_id IN (:orderId, :transactionId)',
            ['orderId' => Uuid::fromHexToBytes($this->orderId), 'transactionId' => Uuid::fromHexToBytes($this->transactionId)]
        );
    }
}

/**
 * @internal
 */
#[Package('after-sales')]
class FailingTransactionalFlowAction extends FlowAction implements TransactionalAction
{
    public int $calls = 0;

    public function __construct(private readonly \Throwable $failure)
    {
    }

    public static function getName(): string
    {
        return 'test.failing.transactional.action';
    }

    public function requirements(): array
    {
        return [];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        ++$this->calls;

        throw $this->failure;
    }
}
