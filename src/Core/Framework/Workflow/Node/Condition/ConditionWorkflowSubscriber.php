<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Event\WorkflowNodePortsEvent;
use Shopwell\Core\Framework\Workflow\Event\WorkflowValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal Definition validation only; this subscriber does not evaluate or execute conditions.
 */
#[Package('framework')]
final class ConditionWorkflowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [WorkflowNodePortsEvent::class => 'resolvePorts', WorkflowValidationEvent::class => 'validate'];
    }

    public function resolvePorts(WorkflowNodePortsEvent $event): void
    {
        if ($event->node->type !== 'core:condition' || $event->node->typeVersion !== 1) {
            return;
        }
        $ports = [];
        foreach ($this->branches($event->node) as $branch) {
            if (\is_array($branch) && \is_string($branch['id'] ?? null) && preg_match('/^branch-[a-zA-Z0-9-]+$/D', $branch['id'])) {
                $ports[] = $branch['id'];
            }
        }
        $event->setPorts(['in'], [...array_values(array_unique($ports)), 'fallback']);
    }

    public function validate(WorkflowValidationEvent $event): void
    {
        foreach ($event->document->nodes as $index => $node) {
            if ($node->type !== 'core:condition' || $node->typeVersion !== 1) {
                continue;
            }
            $path = '/nodes/' . $index . '/config/branches';
            $branches = $this->branches($node);
            if ($branches === []) {
                $event->addViolation(new WorkflowViolation('condition_invalid_branches', $path, $node->id));
                continue;
            }
            $ids = [];
            foreach ($branches as $position => $branch) {
                if (!\is_array($branch) || !\is_string($branch['id'] ?? null) || !preg_match('/^branch-[a-zA-Z0-9-]+$/D', $branch['id']) || isset($ids[$branch['id']])) {
                    $event->addViolation(new WorkflowViolation('condition_invalid_branches', $path . '/' . $position, $node->id));
                    continue;
                }
                $ids[$branch['id']] = true;
                if (!\is_string($branch['label'] ?? null) || trim($branch['label']) === ''
                    || !\is_string($branch['field'] ?? null) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/D', $branch['field'])
                    || !\in_array($branch['operator'] ?? null, ['equals', 'notEquals', 'isEmpty', 'isNotEmpty', 'greaterThan', 'greaterThanOrEqual', 'lessThan', 'lessThanOrEqual'], true)
                    || !\is_string($branch['value'] ?? null)) {
                    $event->addViolation(new WorkflowViolation('condition_invalid_rule', $path . '/' . $position, $node->id));
                }
            }
        }
    }

    /**
     * @return list<mixed>
     */
    private function branches(WorkflowNode $node): array
    {
        $branches = \is_array($node->config) ? ($node->config['branches'] ?? null) : null;

        return \is_array($branches) && array_is_list($branches) ? $branches : [];
    }
}
