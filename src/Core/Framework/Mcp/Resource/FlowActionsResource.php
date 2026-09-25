<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopwell\Core\Content\Flow\Api\FlowActionCollector;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Context\McpContextProvider;
use Shopwell\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopwell://flow-actions',
    name: 'shopwell-flow-actions',
    description: 'All registered Shopwell flow actions (core and app-provided) available in Flow Builder automations.'
)]
class FlowActionsResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FlowActionCollector $collector,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $context = $this->contextProvider->getContext();
        $result = $this->collector->collect($context);

        $actions = [];
        foreach ($result as $action) {
            $actions[] = [
                'name' => $action->getName(),
                'requirements' => $action->getRequirements(),
                'delayable' => $action->getDelayable(),
            ];
        }

        usort($actions, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        return [
            'uri' => 'shopwell://flow-actions',
            'mimeType' => 'application/json',
            'text' => Json::encode($actions),
        ];
    }
}
