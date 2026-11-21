<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
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

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
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

        return McpResourceCacheHint::read([
            'uri' => 'shopwell://flow-actions',
            'mimeType' => 'application/json',
            'text' => Json::encode($actions),
        ], $requestContext, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);
    }
}
