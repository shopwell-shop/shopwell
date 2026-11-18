<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\RoleApprovalValidationSubscriber;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RoleApprovalValidationSubscriber::class)]
class RoleApprovalValidationSubscriberTest extends TestCase
{
    public function testPublicationChecksRolesWithoutFreezingTheirMembers(): void
    {
        $role = new AclRoleEntity();
        $role->setId(Uuid::randomHex());
        $repository = StaticEntityRepository::of(AclRoleCollection::class, [[$role->getId()]]);
        $event = $this->event([$role->getId()]);

        (new RoleApprovalValidationSubscriber($repository))->validate($event);

        static::assertSame([$role->getId()], $repository->searchedCriteria[0]->getIds());
        static::assertSame([], $repository->searchedCriteria[0]->getFilters());
    }

    public function testUnavailableRoleRejectsPublicationAndRevalidation(): void
    {
        $repository = StaticEntityRepository::of(AclRoleCollection::class, [[]]);
        $this->expectExceptionObject(WorkflowException::publicationRejected('role_unavailable'));

        (new RoleApprovalValidationSubscriber($repository))->validate($this->event([Uuid::randomHex()]));
    }

    /**
     * @param list<string> $ids
     */
    private function event(array $ids): ApprovalWorkflowValidationEvent
    {
        $graph = GraphData::fromArray([
            'nodes' => [['id' => 'approval', 'type' => 'snaker:task', 'properties' => [
                'assigneeType' => WorkflowAssigneeTypes::ROLE, 'assigneeValue' => $ids,
            ]]], 'edges' => [],
        ]);

        return new ApprovalWorkflowValidationEvent($graph, Context::createDefaultContext());
    }
}
