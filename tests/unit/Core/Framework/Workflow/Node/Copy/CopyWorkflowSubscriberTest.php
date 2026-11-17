<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Preview\ApprovalNodePreviewEvent;
use Shopwell\Core\Framework\Workflow\Node\Copy\CopyWorkflowSubscriber;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CopyWorkflowSubscriber::class)]
class CopyWorkflowSubscriberTest extends TestCase
{
    public function testRecipientsNeedInstanceAccessButNoDecisionPrivilege(): void
    {
        $recipientId = Uuid::randomHex();
        $role = new AclRoleEntity();
        $role->setId(Uuid::randomHex());
        $role->setPrivileges(['workflow_instance:access']);
        $recipient = new UserEntity();
        $recipient->setId($recipientId);
        $recipient->setAdmin(false);
        $recipient->setAclRoles(new AclRoleCollection([$role]));
        $users = new StaticEntityRepository([new UserCollection([$recipient])]);
        $event = new ApprovalWorkflowValidationEvent($this->graph([$recipientId]), Context::createDefaultContext());

        (new CopyWorkflowSubscriber($users))->validate($event);

        static::assertSame([$recipientId], $users->searchedCriteria[0]->getIds());
        static::assertEquals([new EqualsFilter('active', true)], $users->searchedCriteria[0]->getFilters());
        static::assertTrue($users->searchedCriteria[0]->hasAssociation('aclRoles'));
    }

    public function testUnavailableRecipientRejectsPublication(): void
    {
        $users = new StaticEntityRepository([new UserCollection()]);
        $event = new ApprovalWorkflowValidationEvent($this->graph([Uuid::randomHex()]), Context::createDefaultContext());

        $this->expectExceptionObject(WorkflowException::publicationRejected('copy_recipient_unavailable'));

        (new CopyWorkflowSubscriber($users))->validate($event);
    }

    public function testRecipientWithoutInstanceAccessRejectsPublication(): void
    {
        $recipient = new UserEntity();
        $recipient->setId(Uuid::randomHex());
        $recipient->setAdmin(false);
        $recipient->setAclRoles(new AclRoleCollection());
        $users = new StaticEntityRepository([new UserCollection([$recipient])]);
        $event = new ApprovalWorkflowValidationEvent($this->graph([$recipient->getId()]), Context::createDefaultContext());

        $this->expectExceptionObject(WorkflowException::publicationRejected('copy_recipient_forbidden'));

        (new CopyWorkflowSubscriber($users))->validate($event);
    }

    public function testPreviewUsesRecipientsAndPreservesAPluginOverride(): void
    {
        $recipientId = Uuid::randomHex();
        $graph = $this->graph([$recipientId]);
        $execution = new WorkflowExecutionContext($graph, new WorkflowInstanceEntity(), Context::createDefaultContext(), actorId: null, round: 1);
        $event = new ApprovalNodePreviewEvent($graph->node('copy'), $execution);
        $users = StaticEntityRepository::of(UserCollection::class);
        $subscriber = new CopyWorkflowSubscriber($users);

        $subscriber->preview($event);

        static::assertSame(['type' => 'copy', 'assigneeIds' => [$recipientId], 'rule' => null, 'branch' => null], $event->getPreview());

        $event->setPreview('plugin:delivery', ['external-recipient']);
        $subscriber->preview($event);

        static::assertSame([
            'type' => 'plugin:delivery',
            'assigneeIds' => ['external-recipient'],
            'rule' => null,
            'branch' => null,
        ], $event->getPreview());
        static::assertSame([], $users->searchedCriteria);
    }

    public function testInvalidSnapshotRecipientsAreNotSilentlyOmitted(): void
    {
        $event = new ApprovalWorkflowValidationEvent($this->graph(['invalid']), Context::createDefaultContext());
        $users = StaticEntityRepository::of(UserCollection::class);

        $this->expectExceptionObject(WorkflowException::publicationRejected('invalid_copy_recipients'));

        (new CopyWorkflowSubscriber($users))->validate($event);
    }

    /**
     * @param list<string> $recipientIds
     */
    private function graph(array $recipientIds): GraphData
    {
        return GraphData::fromArray([
            'nodes' => [['id' => 'copy', 'type' => 'snaker:copy', 'properties' => ['userIdList' => $recipientIds]]],
            'edges' => [],
        ]);
    }
}
