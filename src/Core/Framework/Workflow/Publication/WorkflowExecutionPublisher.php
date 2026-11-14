<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionStatus;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowExecutionPublisher implements WorkflowPublisher, EventSubscriberInterface
{
    /**
     * @param EntityRepository<WorkflowExecutionDefinitionCollection> $definitions
     */
    public function __construct(private WorkflowDocumentCodec $codec, private WorkflowCompiler $compiler, private EntityRepository $definitions)
    {
    }

    public function name(): string
    {
        return 'core:workflow';
    }

    public function labelKey(): string
    {
        return 'sw-workflow.publisher.workflow';
    }

    public function privileges(): array
    {
        return [];
    }

    public function publish(WorkflowReleaseEntity $release, Context $context): void
    {
        $graph = $this->compiler->compile($this->codec->decode($release->getDocument()), $context);
        $schema = $graph->singleStartNode()->property('inputSchema', []);
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($release, $graph, $schema): void {
            $this->definitions->create([[
                'id' => $release->getId(),
                'code' => 'workflow-' . $release->getId(),
                'name' => $release->getName(),
                'version' => $release->getRevision(),
                'status' => WorkflowExecutionDefinitionStatus::PUBLISHED,
                'isLatest' => true,
                'graphData' => $graph->toArray(),
                'formSchema' => $schema,
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

        $this->compiler->compile($this->codec->decode($event->release->getDocument()), $event->context);
        $definition = $this->definitions->search(new Criteria([$event->release->getId()]), $event->context)->getEntities()->first();

        if ($definition === null || $definition->status !== WorkflowExecutionDefinitionStatus::PUBLISHED) {
            throw WorkflowException::publicationRejected('workflow_definition_unavailable');
        }
    }
}
