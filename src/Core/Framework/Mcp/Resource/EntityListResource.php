<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopwell://entities',
    name: 'shopwell-entity-list',
    description: 'List of all registered Shopwell entity names'
)]
class EntityListResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
    {
        $entities = [];
        foreach ($this->registry->getDefinitions() as $definition) {
            $entities[] = $definition->getEntityName();
        }

        sort($entities);

        return McpResourceCacheHint::read([
            'uri' => 'shopwell://entities',
            'mimeType' => 'application/json',
            'text' => Json::encode($entities),
        ], $requestContext, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);
    }
}
