<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Context\McpContextProvider;
use Shopwell\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopwell://business-events',
    name: 'shopwell-business-events',
    description: 'All registered Shopwell business events that can trigger flows and event actions.'
)]
class BusinessEventsResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventCollector $collector,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
    {
        $context = $this->contextProvider->getContext();
        $result = $this->collector->collect($context);

        $events = [];
        foreach ($result as $event) {
            $events[] = [
                'name' => $event->getName(),
                'class' => $event->getClass(),
                'data' => $event->getData(),
            ];
        }

        return McpResourceCacheHint::read([
            'uri' => 'shopwell://business-events',
            'mimeType' => 'application/json',
            'text' => Json::encode($events),
        ], $requestContext, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);
    }
}
