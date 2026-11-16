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
        $graph = $this->compile($release, $context);

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
        $this->compile($event->release, $event->context);
        $definition = $this->definitions->search(new Criteria([$event->release->getId()]), $event->context)->getEntities()->first();
        if ($definition === null || $definition->status !== WorkflowExecutionDefinitionStatus::PUBLISHED) {
            throw WorkflowException::publicationRejected('workflow_definition_unavailable');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function compile(WorkflowReleaseEntity $release, Context $context): array
    {
        $graph = $this->compiler->compile($this->codec->decode($release->getDocument()));
        $assigneeIds = [];
        foreach ($graph['nodes'] as $node) {
            $assigneeIds = [...$assigneeIds, ...($node['properties']['assigneeValue'] ?? [])];
        }
        $assigneeIds = array_values(array_unique($assigneeIds));
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

        return $graph;
    }
}
