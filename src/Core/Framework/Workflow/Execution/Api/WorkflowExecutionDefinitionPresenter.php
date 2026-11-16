<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;

/**
 * Build the shared definition representation, optionally including its graph.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowExecutionDefinitionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(WorkflowExecutionDefinitionEntity $definition, Context $context, bool $withGraph = true): array
    {
        $payload = [
            'id' => $definition->id,
            'code' => $definition->code,
            'name' => $definition->name,
            'description' => $definition->description,
            'status' => $definition->status,
            'version' => $definition->version,
            'isLatest' => $definition->isLatest,
            'formSchema' => $definition->formFields(),
            'updatedAt' => $definition->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($withGraph) {
            // Omit large graphs from list responses.
            $payload['graphData'] = $definition->graph();
        }

        return $payload;
    }
}
