<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingCollection;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestCollection;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResult;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultDelivery;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultDispatcher;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultHandler;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultHandlerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultOutbox;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalResultDispatcher::class)]
class ApprovalResultDispatcherTest extends TestCase
{
    private ApprovalRequestEntity $request;

    private ApprovalBindingEntity $binding;

    private ApprovalResultDelivery $delivery;

    protected function setUp(): void
    {
        $this->binding = new ApprovalBindingEntity();
        $this->binding->assign(['id' => Uuid::randomHex(), 'businessVersion' => '1.0.0']);
        $this->request = new ApprovalRequestEntity();
        $this->request->assign(['id' => Uuid::randomHex(), 'instanceId' => Uuid::randomHex(), 'bindingId' => $this->binding->getId(), 'businessType' => 'finance:expense']);
        $this->delivery = new ApprovalResultDelivery(
            new ApprovalResult(Uuid::randomHex(), $this->request->getInstanceId(), Uuid::randomHex(), Uuid::randomHex(), 4, ApprovalOutcome::Approved, new \DateTimeImmutable('2026-11-20 UTC')),
            1,
            new \DateTimeImmutable('2026-11-20 00:01:00 UTC'),
        );
    }

    public function testSuccessfulBusinessCommitIsAcknowledgedAndCounted(): void
    {
        $handler = new ExpenseResultHandler();
        $outbox = static::createMock(ApprovalResultOutbox::class);
        $outbox->method('claim')->willReturnOnConsecutiveCalls($this->delivery, null);
        $outbox->expects($this->once())->method('acknowledge')->with($this->delivery->result->id, 1, static::isInstanceOf(Context::class))->willReturn(true);
        $outbox->expects($this->never())->method('retry');

        static::assertSame(1, $this->dispatcher($outbox, [$handler])->dispatchPending());
        static::assertSame([$this->delivery->result->id], $handler->applied);
    }

    public function testMissingExactBusinessHandlerDoesNotAcknowledgeTheResult(): void
    {
        $handler = new ExpenseResultHandler('2.0.0');
        $outbox = static::createMock(ApprovalResultOutbox::class);
        $outbox->method('claim')->willReturnOnConsecutiveCalls($this->delivery, null);
        $outbox->expects($this->never())->method('acknowledge');
        $outbox->expects($this->once())->method('retry')->with($this->delivery->result->id, 1, static::isInstanceOf(Context::class))->willReturn(true);

        static::assertSame(0, $this->dispatcher($outbox, [$handler])->dispatchPending());
        static::assertSame([], $handler->applied);
    }

    public function testMissingBusinessRequestCannotBecomeDelivered(): void
    {
        $outbox = static::createMock(ApprovalResultOutbox::class);
        $outbox->method('claim')->willReturnOnConsecutiveCalls($this->delivery, null);
        $outbox->expects($this->never())->method('acknowledge');
        $outbox->expects($this->once())->method('retry')->willReturn(true);
        $dispatcher = new ApprovalResultDispatcher(
            $outbox,
            new StaticEntityRepository([new ApprovalRequestCollection()]),
            new StaticEntityRepository([new ApprovalBindingCollection()]),
            new ApprovalResultHandlerRegistry([]),
            new NullLogger(),
        );

        static::assertSame(0, $dispatcher->dispatchPending());
    }

    public function testAnExpiredConfirmationIsNotCountedAsDelivered(): void
    {
        $handler = new ExpenseResultHandler();
        $outbox = static::createStub(ApprovalResultOutbox::class);
        $outbox->method('claim')->willReturn($this->delivery);
        $outbox->method('acknowledge')->willReturn(false);

        static::assertSame(0, $this->dispatcher($outbox, [$handler])->dispatchPending(limit: 1));
        static::assertSame([$this->delivery->result->id], $handler->applied);
    }

    public function testFailedBusinessHandlerDoesNotBlockTheNextDelivery(): void
    {
        $handler = new ExpenseResultHandler(failFirst: true);
        $next = new ApprovalResultDelivery(
            new ApprovalResult(Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), 4, ApprovalOutcome::Rejected, new \DateTimeImmutable('2026-11-20 UTC')),
            1,
            new \DateTimeImmutable('2026-11-20 00:01:00 UTC'),
        );
        $request = clone $this->request;
        $request->setInstanceId($next->result->instanceId);
        $outbox = static::createMock(ApprovalResultOutbox::class);
        $outbox->method('claim')->willReturnOnConsecutiveCalls($this->delivery, $next, null);
        $outbox->expects($this->once())->method('retry')->with($this->delivery->result->id, 1, static::isInstanceOf(Context::class))->willReturn(true);
        $outbox->expects($this->once())->method('acknowledge')->with($next->result->id, 1, static::isInstanceOf(Context::class))->willReturn(true);
        $dispatcher = new ApprovalResultDispatcher(
            $outbox,
            new StaticEntityRepository([new ApprovalRequestCollection([$this->request]), new ApprovalRequestCollection([$request])]),
            new StaticEntityRepository([new ApprovalBindingCollection([$this->binding]), new ApprovalBindingCollection([$this->binding])]),
            new ApprovalResultHandlerRegistry([$handler]),
            new NullLogger(),
        );

        static::assertSame(1, $dispatcher->dispatchPending());
        static::assertSame([$next->result->id], $handler->applied);
    }

    /**
     * @param list<ApprovalResultHandler> $handlers
     */
    private function dispatcher(ApprovalResultOutbox $outbox, array $handlers): ApprovalResultDispatcher
    {
        return new ApprovalResultDispatcher(
            $outbox,
            new StaticEntityRepository([new ApprovalRequestCollection([$this->request]), new ApprovalRequestCollection([$this->request])]),
            new StaticEntityRepository([new ApprovalBindingCollection([$this->binding]), new ApprovalBindingCollection([$this->binding])]),
            new ApprovalResultHandlerRegistry($handlers),
            new NullLogger(),
        );
    }
}

/**
 * @internal
 */
class ExpenseResultHandler implements ApprovalResultHandler
{
    /**
     * @var list<string>
     */
    public array $applied = [];

    public function __construct(private readonly string $version = '1.0.0', private bool $failFirst = false)
    {
    }

    public function getType(): string
    {
        return 'finance:expense';
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function handle(ApprovalResult $result, ApprovalRequestEntity $request, Context $context): void
    {
        if ($this->failFirst) {
            $this->failFirst = false;
            throw new \RuntimeException('Expense storage unavailable.');
        }
        $this->applied[] = $result->id;
    }
}
