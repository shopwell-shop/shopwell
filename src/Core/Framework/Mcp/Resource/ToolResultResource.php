<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Server\RequestContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\McpException;
use Shopwell\Core\Framework\Mcp\ToolResultCacheStorage;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Serves a large tool result that was stored during the current MCP session.
 * Access is restricted to the session that created the result.
 */
#[Package('framework')]
#[McpResourceTemplate(
    uriTemplate: 'shopwell://tool-result/{id}',
    name: 'tool-result',
    description: 'Retrieves a large tool result stored by a previous tool call in this session. Fetch when a tool response contains a resourceUri pointing here.',
    mimeType: 'application/json',
)]
class ToolResultResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ToolResultCacheStorage $storage,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(string $id, RequestContext $context): array
    {
        $sessionId = $context->getSession()->getId()->toString();
        $result = $this->storage->read($id, $sessionId);

        if ($result === null) {
            throw McpException::toolResultNotFound($id);
        }

        return [
            'uri' => 'shopwell://tool-result/' . $id,
            'mimeType' => $result['mimeType'],
            'text' => $result['content'],
        ];
    }
}
