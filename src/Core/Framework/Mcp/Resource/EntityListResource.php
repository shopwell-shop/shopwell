<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopware://entities',
    name: 'shopware-entity-list',
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

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $entities = [];
        foreach ($this->registry->getDefinitions() as $definition) {
            $entities[] = $definition->getEntityName();
        }

        sort($entities);

        return [
            'uri' => 'shopware://entities',
            'mimeType' => 'application/json',
            'text' => Json::encode($entities),
        ];
    }
}
