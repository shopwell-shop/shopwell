<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionStatus;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseCheckEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\User\UserCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalWorkflowPublisher implements WorkflowPublisher, EventSubscriberInterface
{
    public const NAME = 'core:approval';

    /**
     * @param EntityRepository<WorkflowExecutionDefinitionCollection> $definitions
     * @param EntityRepository<UserCollection> $users
     */
    public function __construct(
        private WorkflowDocumentCodec $codec,
        private ApprovalWorkflowCompiler $compiler,
        private EntityRepository $definitions,
        private EntityRepository $users,
        private WorkflowGraphValidator $validator,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function labelKey(): string
    {
        return 'sw-workflow.publisher.approval';
    }

    public function privileges(): array
    {
        return [];
    }

    public function publish(WorkflowReleaseEntity $release, Context $context): void
    {
        $graph = $this->compiler->compile($this->codec->decode($release->getDocument()));
        $this->validateGraph($graph, $graph['nodes'][0]['properties']['inputSchema'], $context);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($release, $graph): void {
            $this->definitions->create([[
                'id' => $release->getId(),
                'code' => 'workflow-' . $release->getId(),
                'version' => $release->getRevision(),
                'name' => $release->getName(),
                'status' => WorkflowExecutionDefinitionStatus::PUBLISHED,
                'isLatest' => true,
                'graphData' => $graph,
                'formSchema' => $graph['nodes'][0]['properties']['inputSchema'],
            ]], $scoped);
        });
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowReleaseCheckEvent::class => 'check'];
    }

    public function check(WorkflowReleaseCheckEvent $event): void
    {
        if ($event->release->getPublisher() !== $this->name()) {
            return;
        }

        $definition = $this->definitions->search(new Criteria([$event->release->getId()]), $event->context)->getEntities()->first();

        if ($definition === null || $definition->status !== WorkflowExecutionDefinitionStatus::PUBLISHED) {
            throw WorkflowException::publicationRejected('workflow_definition_unavailable');
        }

        $this->validateGraph($definition->graph(), $definition->formFields(), $event->context);
    }

    /**
     * @param array<string, mixed> $graph
     * @param list<array<string, mixed>> $formSchema
     */
    private function validateGraph(array $graph, array $formSchema, Context $context): void
    {
        $violations = $this->validator->validate(GraphData::fromArray($graph), ValidationContext::fromFormSchema($formSchema));

        if ($this->validator->hasErrors($violations)) {
            throw WorkflowExecutionException::graphInvalid(array_map(
                static fn ($violation): array => [
                    'code' => $violation->code,
                    'message' => $violation->message,
                    'nodeId' => $violation->nodeId,
                    'edgeId' => $violation->edgeId,
                ],
                $violations
            ));
        }

        $this->validateStaticAssignees($graph, $context);
    }

    /**
     * @param array<string, mixed> $graph
     */
    private function validateStaticAssignees(array $graph, Context $context): void
    {
        $assigneeIds = [];

        foreach ($graph['nodes'] as $node) {
            $assigneeIds = [...$assigneeIds, ...($node['properties']['assigneeUserIds'] ?? [])];
        }

        $assigneeIds = array_values(array_unique($assigneeIds));

        if ($assigneeIds === []) {
            return;
        }

        $criteria = new Criteria($assigneeIds);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('aclRoles');
        $users = $this->users->search($criteria, $context)->getEntities();

        if (array_diff($assigneeIds, $users->getIds()) !== []) {
            throw WorkflowException::publicationRejected('approval_assignee_unavailable');
        }

        foreach ($users as $user) {
            if ($user->isAdmin()) {
                continue;
            }

            $privileges = [];
            foreach ($user->getAclRoles() ?? [] as $role) {
                $privileges = [...$privileges, ...$role->getPrivileges()];
            }

            if (array_diff(['workflow_instance:access', 'workflow_task:decide'], $privileges) !== []) {
                throw WorkflowException::publicationRejected('approval_assignee_forbidden');
            }
        }
    }
}
