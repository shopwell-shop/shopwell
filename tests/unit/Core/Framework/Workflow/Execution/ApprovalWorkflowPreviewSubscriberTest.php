<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowPreviewSubscriber;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ComparisonConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Condition\WorkflowConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceMetadataEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Preview\ApprovalNodePreviewEvent;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimePreviewEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\QuorumApprovalMode;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalWorkflowPreviewSubscriber::class)]
class ApprovalWorkflowPreviewSubscriberTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('amounts')]
    public function testPreviewReturnsOnlyTheOrderedMatchingPath(string $amount, string $currency, array $expected, string $branch): void
    {
        $definitions = new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$this->definition()])]);
        $user = new UserEntity();
        $user->setId('reviewer');
        $user->setName('Finance Reviewer');
        $users = new StaticEntityRepository([new UserCollection([$user])]);
        $event = $this->event(['amount' => $amount, 'currency' => $currency]);
        $this->subscriber($definitions, $users)->preview($event);
        $steps = $event->getSteps();
        static::assertNotNull($steps);
        static::assertSame($expected, array_column($steps, 'nodeId'));
        static::assertSame($branch, $steps[1]['branch']);
        static::assertSame([['id' => 'reviewer', 'name' => 'Finance Reviewer']], $steps[0]['assignees']);
        static::assertSame('countersign', $steps[0]['rule']);
        static::assertCount(1, $definitions->searchedCriteria);
        static::assertCount(1, $users->searchedCriteria);
    }

    public static function amounts(): \Generator
    {
        yield 'threshold uses standard path' => ['5000', 'CNY', ['finance', 'policy'], 'Standard'];
        yield 'one cent above threshold needs review' => ['5000.01', 'CNY', ['finance', 'policy', 'amount'], 'Large amount'];
        yield 'foreign currency wins overlapping conditions' => ['6000', 'USD', ['finance', 'policy', 'foreign'], 'Foreign currency'];
    }

    public function testDisabledDefinitionIsNotPresentedAsAnExecutablePath(): void
    {
        $definition = $this->definition();
        $definition->status = 'draft';
        $subscriber = $this->subscriber(new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]), StaticEntityRepository::of(UserCollection::class));
        $this->expectExceptionObject(WorkflowException::publicationRejected('workflow_definition_unavailable'));
        $subscriber->preview($this->event(['amount' => '1', 'currency' => 'CNY']));
    }

    public function testAnotherPublisherRemainsAvailableForAnExtensionSubscriber(): void
    {
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class);
        $event = $this->event([]);
        $event->release->assign(['publisher' => 'extension:runtime']);
        $this->subscriber($definitions, StaticEntityRepository::of(UserCollection::class))->preview($event);
        static::assertNull($event->getSteps());
        static::assertSame([], $definitions->searchedCriteria);
    }

    public function testEmptyAssigneesBlockSubmissionInsteadOfDisablingOnlyThePreview(): void
    {
        $definitions = new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$this->definition()])]);
        $resolver = static::createStub(AssigneeResolverInterface::class);
        $resolver->method('getAssigneeType')->willReturn('user');
        $resolver->method('resolve')->willReturn([]);
        $subscriber = $this->subscriber($definitions, StaticEntityRepository::of(UserCollection::class), $resolver);

        $this->expectExceptionObject(WorkflowException::publicationRejected('approval_assignee_unavailable'));

        $subscriber->preview($this->event(['amount' => '1', 'currency' => 'CNY']));
    }

    #[DataProvider('resolverFailures')]
    public function testResolverFailuresRetainTheResponsibleDomainOutcome(\Exception $failure, \Exception $expected): void
    {
        $resolver = static::createStub(AssigneeResolverInterface::class);
        $resolver->method('getAssigneeType')->willReturn('user');
        $resolver->method('resolve')->willThrowException($failure);
        $subscriber = $this->subscriber(
            new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$this->definition()])]),
            StaticEntityRepository::of(UserCollection::class),
            $resolver,
        );
        $this->expectExceptionObject($expected);

        $subscriber->preview($this->event(['amount' => '1', 'currency' => 'CNY']));
    }

    public static function resolverFailures(): \Generator
    {
        yield 'unavailable organization member has an actionable reason' => [
            WorkflowExecutionException::noAssignee('finance', 'user'),
            WorkflowException::publicationRejected('approval_assignee_unavailable'),
        ];
        $unsupported = WorkflowExecutionException::unsupportedAssigneeType('extension:missing');
        yield 'unrelated resolver error remains visible' => [$unsupported, $unsupported];
    }

    public function testFormDependentResolverReceivesTheNormalizedPreviewForm(): void
    {
        $resolver = static::createStub(AssigneeResolverInterface::class);
        $resolver->method('getAssigneeType')->willReturn('user');
        $resolver->method('resolve')->willReturnCallback(static function (WorkflowInstanceEntity $instance): array {
            return ($instance->form()['currency'] ?? null) === 'CNY' && isset($instance->snapshot()['nodes']) ? ['reviewer'] : [];
        });
        $event = $this->event(['amount' => '1', 'currency' => 'CNY']);
        $subscriber = $this->subscriber(
            new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$this->definition()])]),
            new StaticEntityRepository([new UserCollection()]),
            $resolver,
        );

        $subscriber->preview($event);

        $steps = $event->getSteps();
        static::assertNotNull($steps);
        static::assertArrayHasKey(0, $steps);
        static::assertSame([['id' => 'reviewer', 'name' => 'reviewer']], $steps[0]['assignees']);
    }

    public function testPreviewCollectsExtensionMetadataBeforeResolvingParticipantsWithoutWrites(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkflowInstanceMetadataEvent::class, static function (WorkflowInstanceMetadataEvent $event): void {
            $event->addMetadata('example:policy', ['revision' => 3]);
        });
        $resolver = static::createMock(AssigneeResolverInterface::class);
        $resolver->method('getAssigneeType')->willReturn('user');
        $resolver->expects($this->once())->method('resolve')->willReturnCallback(static function (WorkflowInstanceEntity $instance): array {
            static::assertSame(['example:policy' => ['revision' => 3]], $instance->ext);

            return ['reviewer'];
        });
        $definitions = new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$this->definition()])]);
        $users = new StaticEntityRepository([new UserCollection()]);
        $event = $this->event(['amount' => '1', 'currency' => 'CNY']);

        $this->subscriber($definitions, $users, $resolver, $dispatcher)->preview($event);

        static::assertNotNull($event->getSteps());
        static::assertSame([], $definitions->creates);
        static::assertSame([], $definitions->updates);
        static::assertSame([], $users->creates);
        static::assertSame([], $users->updates);
    }

    public function testPreviewUsesTheVersionedPluginMode(): void
    {
        $definition = $this->definition();
        $definition->graphData['nodes'][1]['properties']['countersignType'] = 'example:quorum';
        $definition->graphData['nodes'][1]['properties']['approvalModeVersion'] = 2;
        $definition->graphData['nodes'][1]['properties']['approvalModeConfig'] = ['required' => 2];
        $event = $this->event(['amount' => '1', 'currency' => 'CNY']);

        $this->subscriber(
            new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]),
            new StaticEntityRepository([new UserCollection()])
        )->preview($event);

        $steps = $event->getSteps();
        static::assertNotNull($steps);
        static::assertArrayHasKey(0, $steps);
        static::assertSame('example:quorum', $steps[0]['rule']);
    }

    public function testPluginCanPreviewAnAdditionalNodeWithoutChangingTheTraversal(): void
    {
        $definition = $this->definition();
        $definition->graphData['nodes'][1]['type'] = 'example:review';
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ApprovalNodePreviewEvent::class, static function (ApprovalNodePreviewEvent $event): void {
            if ($event->node->type === 'example:review') {
                $event->setPreview('example:review', ['plugin-reviewer'], rule: 'example:policy');
            }
        });
        $preview = $this->event(['amount' => '1', 'currency' => 'CNY']);
        $this->subscriber(
            new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]),
            new StaticEntityRepository([new UserCollection()]),
            dispatcher: $dispatcher,
        )->preview($preview);

        $steps = $preview->getSteps();
        static::assertNotNull($steps);
        static::assertSame('example:review', $steps[0]['type']);
        static::assertSame('example:policy', $steps[0]['rule']);
        static::assertSame([['id' => 'plugin-reviewer', 'name' => 'plugin-reviewer']], $steps[0]['assignees']);
    }

    public function testUnsupportedNodeCannotSilentlyDisappearFromThePreview(): void
    {
        $definition = $this->definition();
        $definition->graphData['nodes'][1]['type'] = 'example:unknown';
        $subscriber = $this->subscriber(
            new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]),
            StaticEntityRepository::of(UserCollection::class),
        );
        $this->expectExceptionObject(WorkflowException::publicationRejected('preview_unavailable'));
        $subscriber->preview($this->event(['amount' => '1', 'currency' => 'CNY']));
    }

    /**
     * @param StaticEntityRepository<WorkflowExecutionDefinitionCollection> $definitions
     * @param StaticEntityRepository<UserCollection> $users
     */
    private function subscriber(StaticEntityRepository $definitions, StaticEntityRepository $users, ?AssigneeResolverInterface $resolver = null, ?EventDispatcher $dispatcher = null): ApprovalWorkflowPreviewSubscriber
    {
        if ($resolver === null) {
            $resolver = static::createStub(AssigneeResolverInterface::class);
            $resolver->method('getAssigneeType')->willReturn('user');
            $resolver->method('resolve')->willReturn(['reviewer']);
        }

        $conditionEvaluator = new WorkflowConditionEvaluator(
            new ComparisonConditionEvaluator(new ExprNormalizer()),
            new ApprovalCondition()
        );

        return new ApprovalWorkflowPreviewSubscriber(
            $definitions,
            new ApprovalRoute($conditionEvaluator),
            new WorkflowInputSchema(),
            new WorkflowUserLookup($users),
            new AssigneeResolverRegistry([$resolver]),
            ApprovalRegistryFixture::modes([new QuorumApprovalMode()]),
            $dispatcher ?? new EventDispatcher(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function event(array $data): WorkflowRuntimePreviewEvent
    {
        $release = new WorkflowReleaseEntity();
        $release->assign(['id' => 'release', 'publisher' => 'core:approval']);

        return new WorkflowRuntimePreviewEvent($release, $data, Context::createDefaultContext());
    }

    private function definition(): WorkflowExecutionDefinitionEntity
    {
        $schema = [['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true], ['key' => 'currency', 'label' => 'Currency', 'type' => 'text', 'required' => true]];
        $conditions = new ApprovalCondition();
        $foreign = $conditions->encode(['field' => 'request.currency', 'fieldType' => 'text', 'operator' => 'notEquals', 'value' => 'CNY'], $schema);
        $large = $conditions->encode(['field' => 'request.amount', 'fieldType' => 'number', 'operator' => 'greaterThan', 'value' => '5000'], $schema);
        $definition = new WorkflowExecutionDefinitionEntity();
        $definition->setUniqueIdentifier('release');
        $definition->assign(['id' => 'release', 'status' => 'published', 'formSchema' => $schema, 'graphData' => ['nodes' => [
            ['id' => 'start', 'type' => 'snaker:start'],
            ['id' => 'finance', 'type' => 'snaker:task', 'properties' => ['name' => 'Finance review', 'assigneeType' => 'user', 'assigneeValue' => ['reviewer'], 'countersignType' => 'countersign']],
            ['id' => 'policy', 'type' => 'snaker:condition', 'properties' => ['name' => 'Review policy']],
            ['id' => 'foreign', 'type' => 'snaker:task', 'properties' => ['name' => 'Foreign review', 'assigneeType' => 'user', 'assigneeValue' => ['reviewer'], 'countersignType' => 'single']],
            ['id' => 'amount', 'type' => 'snaker:task', 'properties' => ['name' => 'Amount review', 'assigneeType' => 'user', 'assigneeValue' => ['reviewer'], 'countersignType' => 'single']],
            ['id' => 'end', 'type' => 'snaker:end'],
        ], 'edges' => [
            ['id' => 'begin', 'sourceNodeId' => 'start', 'targetNodeId' => 'finance'],
            ['id' => 'review', 'sourceNodeId' => 'finance', 'targetNodeId' => 'policy'],
            ['id' => 'default', 'sourceNodeId' => 'policy', 'targetNodeId' => 'end', 'properties' => ['name' => 'Standard']],
            ['id' => 'foreign-branch', 'sourceNodeId' => 'policy', 'targetNodeId' => 'foreign', 'properties' => ['name' => 'Foreign currency', 'expr' => $foreign]],
            ['id' => 'large-branch', 'sourceNodeId' => 'policy', 'targetNodeId' => 'amount', 'properties' => ['name' => 'Large amount', 'expr' => $large]],
            ['id' => 'foreign-end', 'sourceNodeId' => 'foreign', 'targetNodeId' => 'end'],
            ['id' => 'amount-end', 'sourceNodeId' => 'amount', 'targetNodeId' => 'end'],
        ]]]);

        return $definition;
    }
}
