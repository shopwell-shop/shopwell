<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeContext;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeLookup;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceMetadataEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\User\Aggregate\UserOrganizationUnit\UserOrganizationUnitEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OrganizationAssigneeContext::class)]
class OrganizationAssigneeContextTest extends TestCase
{
    public function testRegisteredSubscriberCapturesDepartmentOnlyOnce(): void
    {
        $departmentId = Uuid::randomHex();
        $membership = new UserOrganizationUnitEntity();
        $membership->setOrganizationUnitId($departmentId);
        $lookup = static::createMock(OrganizationAssigneeLookup::class);
        $context = Context::createDefaultContext();
        $lookup->expects($this->once())->method('primaryMembership')->with('initiator', $context)->willReturn($membership);
        $organizations = new OrganizationAssigneeContext($lookup);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($organizations);
        $event = new WorkflowInstanceMetadataEvent('initiator', $context);
        $dispatcher->dispatch($event);
        $instance = new WorkflowInstanceEntity();
        $instance->ext = $event->getMetadata();

        static::assertSame(['core:organization' => ['departmentId' => $departmentId]], $instance->ext);
        static::assertSame($departmentId, $organizations->departmentId($instance));
        static::assertSame($departmentId, $organizations->departmentId($instance));
    }

    public function testMissingDepartmentIsCapturedExplicitlyAndNeverResolvedAgain(): void
    {
        $lookup = static::createMock(OrganizationAssigneeLookup::class);
        $lookup->expects($this->once())->method('primaryMembership')->willReturn(null);
        $organizations = new OrganizationAssigneeContext($lookup);
        $event = new WorkflowInstanceMetadataEvent('initiator', Context::createDefaultContext());
        $organizations->capture($event);
        $instance = new WorkflowInstanceEntity();
        $instance->ext = $event->getMetadata();

        static::assertSame(['core:organization' => ['departmentId' => null]], $instance->ext);
        static::assertNull($organizations->departmentId($instance));
    }

    public function testPreviewWithoutAnInitiatorDoesNotQueryAnInvalidUserId(): void
    {
        $lookup = static::createMock(OrganizationAssigneeLookup::class);
        $lookup->expects($this->never())->method('primaryMembership');
        $event = new WorkflowInstanceMetadataEvent(null, Context::createDefaultContext());

        (new OrganizationAssigneeContext($lookup))->capture($event);

        static::assertSame(['core:organization' => ['departmentId' => null]], $event->getMetadata());
    }

    public function testAbsentSnapshotDoesNotFallBackToCurrentMembership(): void
    {
        $lookup = static::createMock(OrganizationAssigneeLookup::class);
        $lookup->expects($this->never())->method('primaryMembership');

        static::assertNull((new OrganizationAssigneeContext($lookup))->departmentId(new WorkflowInstanceEntity()));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSnapshots(): iterable
    {
        yield 'scalar metadata' => ['invalid'];
        yield 'missing department' => [[]];
        yield 'invalid department' => [['departmentId' => 'invalid']];
        yield 'wrong department type' => [['departmentId' => 42]];
    }

    #[DataProvider('invalidSnapshots')]
    public function testMalformedSnapshotCannotResolveAnotherDepartment(mixed $metadata): void
    {
        $lookup = static::createMock(OrganizationAssigneeLookup::class);
        $lookup->expects($this->never())->method('primaryMembership');
        $instance = new WorkflowInstanceEntity();
        $instance->ext = ['core:organization' => $metadata];

        $this->expectException(WorkflowExecutionException::class);
        (new OrganizationAssigneeContext($lookup))->departmentId($instance);
    }
}
