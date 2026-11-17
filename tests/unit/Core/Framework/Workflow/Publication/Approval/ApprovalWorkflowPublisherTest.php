<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseCheckEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalWorkflowPublisher::class)]
class ApprovalWorkflowPublisherTest extends TestCase
{
    public function testCreatesPublishedDefinitionBoundToRelease(): void
    {
        $release = $this->release();
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $users = $this->users(['0123456789abcdef0123456789abcdef']);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $users,
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );

        $publisher->publish($release, Context::createDefaultContext());

        static::assertSame('core:approval', $publisher->name());
        static::assertSame('sw-workflow.publisher.approval', $publisher->labelKey());
        static::assertSame([], $publisher->privileges());
        static::assertCount(1, $definitions->creates);
        $definition = $definitions->creates[0][0];
        static::assertSame($release->getId(), $definition['id']);
        static::assertSame('workflow-' . $release->getId(), $definition['code']);
        static::assertSame(3, $definition['version']);
        static::assertSame('Expense approval', $definition['name']);
        static::assertSame('published', $definition['status']);
        static::assertEquals([new EqualsFilter('active', true)], $users->searchedCriteria[0]->getFilters());
    }

    public function testUnavailableApproverCannotCreateDefinition(): void
    {
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $users = $this->users([]);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $users,
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );

        $this->expectExceptionObject(WorkflowException::publicationRejected('approval_assignee_unavailable'));
        try {
            $publisher->publish($this->release(), Context::createDefaultContext());
        } finally {
            static::assertSame([], $definitions->creates);
        }
    }

    public function testMissingOneOfMultipleReviewersCannotShrinkTheQuorum(): void
    {
        $release = $this->release();
        $document = json_decode($release->getDocument(), true, flags: \JSON_THROW_ON_ERROR);
        $document['nodes'][1]['config'] = [
            'assignee' => ['source' => 'core:users', 'config' => ['userIds' => ['0123456789abcdef0123456789abcdef', 'abcdef0123456789abcdef0123456789']]],
            'approval' => ['mode' => 'core:all', 'config' => []],
        ];
        $release->setDocument(json_encode($document, \JSON_THROW_ON_ERROR));
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $users = $this->users(['0123456789abcdef0123456789abcdef']);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $users,
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );

        $this->expectExceptionObject(WorkflowException::publicationRejected('approval_assignee_unavailable'));
        try {
            $publisher->publish($release, Context::createDefaultContext());
        } finally {
            static::assertSame([], $definitions->creates);
        }
    }

    public function testUnavailableFutureStageReviewerPreventsPublication(): void
    {
        $release = $this->release();
        $document = json_decode($release->getDocument(), true, flags: \JSON_THROW_ON_ERROR);
        $second = $document['nodes'][1];
        $second['id'] = 'second';
        $second['config']['assignee']['config']['userId'] = 'abcdef0123456789abcdef0123456789';
        $document['nodes'][] = $second;
        $document['connections'][1]['target'] = 'second';
        $document['connections'][] = ['id' => 'second-accept', 'source' => 'second', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'];
        $document['connections'][] = ['id' => 'second-reject', 'source' => 'second', 'sourcePort' => 'rejected', 'target' => 'end', 'targetPort' => 'in'];
        $release->setDocument(json_encode($document, \JSON_THROW_ON_ERROR));
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $users = $this->users(['0123456789abcdef0123456789abcdef']);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $users,
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );
        $this->expectExceptionObject(WorkflowException::publicationRejected('approval_assignee_unavailable'));
        try {
            $publisher->publish($release, Context::createDefaultContext());
        } finally {
            static::assertSame([], $definitions->creates);
            static::assertSame(['0123456789abcdef0123456789abcdef', 'abcdef0123456789abcdef0123456789'], $users->searchedCriteria[0]->getIds());
        }
    }

    public function testActiveReviewerWithoutApprovalPrivilegesCannotPublish(): void
    {
        $user = new UserEntity();
        $user->setId('0123456789abcdef0123456789abcdef');
        $user->setActive(true);
        $user->setAdmin(false);
        $users = new StaticEntityRepository([new UserCollection([$user])]);
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $users,
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );

        $this->expectExceptionObject(WorkflowException::publicationRejected('approval_assignee_forbidden'));
        try {
            $publisher->publish($this->release(), Context::createDefaultContext());
        } finally {
            static::assertSame([], $definitions->creates);
        }
    }

    public function testReviewerCanCombineReadAndDecisionGrantsFromDifferentRoles(): void
    {
        $user = new UserEntity();
        $user->setId('0123456789abcdef0123456789abcdef');
        $user->setActive(true);
        $user->setAdmin(false);
        $roles = [];
        foreach (['workflow_instance:access', 'workflow_task:decide'] as $privilege) {
            $role = new AclRoleEntity();
            $role->setId(md5($privilege));
            $role->setPrivileges([$privilege]);
            $roles[] = $role;
        }
        $user->setAclRoles(new AclRoleCollection($roles));
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            new StaticEntityRepository([new UserCollection([$user])]),
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );
        $publisher->publish($this->release(), Context::createDefaultContext());
        static::assertCount(1, $definitions->creates);
    }

    public function testReleaseRecheckUsesThePublishedSnapshotInsteadOfRecompilingOverrides(): void
    {
        $release = $this->release();
        $definition = new WorkflowExecutionDefinitionEntity();
        $definition->id = $release->getId();
        $definition->setUniqueIdentifier($definition->id);
        $definition->status = 'published';
        $definition->graphData = ApprovalRegistryFixture::compiler()->compile(
            (new WorkflowDocumentCodec(Validation::createValidator()))->decode($release->getDocument())
        );
        $definitions = new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $this->users(['0123456789abcdef0123456789abcdef']),
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );
        $release->setDocument('{}');

        $publisher->check(new WorkflowReleaseCheckEvent($release, Context::createDefaultContext()));

        static::assertCount(1, $definitions->searchedCriteria);
    }

    public function testReleaseRecheckRejectsAnUnavailableReviewer(): void
    {
        $release = $this->release();
        $definition = new WorkflowExecutionDefinitionEntity();
        $definition->id = $release->getId();
        $definition->setUniqueIdentifier($definition->id);
        $definition->status = 'published';
        $definition->graphData = ApprovalRegistryFixture::compiler()->compile(
            (new WorkflowDocumentCodec(Validation::createValidator()))->decode($release->getDocument())
        );
        $definitions = new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $this->users([]),
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );
        $this->expectExceptionObject(WorkflowException::publicationRejected('approval_assignee_unavailable'));
        $publisher->check(new WorkflowReleaseCheckEvent($this->release(), Context::createDefaultContext()));
    }

    public function testReleaseRecheckRejectsADisabledExecutionDefinition(): void
    {
        $definition = new WorkflowExecutionDefinitionEntity();
        $definition->id = $this->release()->getId();
        $definition->setUniqueIdentifier($definition->id);
        $definition->status = 'disabled';
        $definitions = new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]);
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $this->users(['0123456789abcdef0123456789abcdef']),
            new WorkflowGraphValidator([]),
            new EventDispatcher(),
        );
        $this->expectExceptionObject(WorkflowException::publicationRejected('workflow_definition_unavailable'));
        $publisher->check(new WorkflowReleaseCheckEvent($this->release(), Context::createDefaultContext()));
    }

    public function testNodeExtensionCanRejectPublicationBeforeTheDefinitionIsWritten(): void
    {
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ApprovalWorkflowValidationEvent::class, static function (): void {
            throw WorkflowException::publicationRejected('example_node_unavailable');
        });
        $publisher = new ApprovalWorkflowPublisher(
            new WorkflowDocumentCodec(Validation::createValidator()),
            ApprovalRegistryFixture::compiler(),
            $definitions,
            $this->users(['0123456789abcdef0123456789abcdef']),
            new WorkflowGraphValidator([]),
            $dispatcher,
        );

        try {
            $publisher->publish($this->release(), Context::createDefaultContext());
            static::fail('Unavailable plugin prerequisites must prevent publication.');
        } catch (WorkflowException $exception) {
            static::assertSame('example_node_unavailable', $exception->getParameters()['reason']);
        }

        static::assertSame([], $definitions->creates);
    }

    /**
     * @param list<string> $ids
     *
     * @return StaticEntityRepository<UserCollection>
     */
    private function users(array $ids): StaticEntityRepository
    {
        $users = new UserCollection();
        foreach ($ids as $id) {
            $user = new UserEntity();
            $user->setId($id);
            $user->setAdmin(true);
            $user->setActive(true);
            $users->add($user);
        }

        return new StaticEntityRepository([$users]);
    }

    private function release(): WorkflowReleaseEntity
    {
        $release = new WorkflowReleaseEntity();
        $release->assign([
            'id' => 'abcdef0123456789abcdef0123456789',
            'draftId' => '0123456789abcdef0123456789abcdef',
            'revision' => 3,
            'name' => 'Expense approval',
            'publisher' => 'core:approval',
            'document' => json_encode([
                'schemaVersion' => 1,
                'policy' => 'core:structured',
                'nodes' => [
                    ['id' => 'start', 'type' => 'core:start', 'typeVersion' => 1, 'config' => []],
                    ['id' => 'approval', 'type' => 'core:approval', 'typeVersion' => 1, 'config' => [
                        'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
                        'approval' => ['mode' => 'core:single', 'config' => []],
                    ]],
                    ['id' => 'end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []],
                ],
                'connections' => [
                    ['id' => 'begin', 'source' => 'start', 'sourcePort' => 'out', 'target' => 'approval', 'targetPort' => 'in'],
                    ['id' => 'accept', 'source' => 'approval', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'],
                    ['id' => 'reject', 'source' => 'approval', 'sourcePort' => 'rejected', 'target' => 'end', 'targetPort' => 'in'],
                ],
            ], \JSON_THROW_ON_ERROR),
        ]);

        return $release;
    }
}
