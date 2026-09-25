<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
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

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
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

        return [
            'uri' => 'shopwell://business-events',
            'mimeType' => 'application/json',
            'text' => Json::encode($events),
        ];
    }
}
