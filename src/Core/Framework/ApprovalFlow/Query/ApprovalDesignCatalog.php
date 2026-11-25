<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Query;

use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * Projects registered declarations for design tools, without asserting executor or publication availability.
 *
 * @phpstan-type NodeDescriptor array{key: string, type: string, version: string, labelSnippet: string, configurationSchema: string, configurationSchemaId: string, configurationSchemaHash: string, draftConfigurationSchema: string, ports: \stdClass, allowedEffects: list<string>}
 * @phpstan-type BusinessDescriptor array{key: string, type: string, version: string, labelSnippet: string, formSchema: string, formSchemaId: string, formSchemaHash: string}
 *
 * @internal
 */
#[Package('framework')]
class ApprovalDesignCatalog
{
    public function __construct(
        private readonly NodeTypeRegistry $nodes,
        private readonly ApprovalBusinessTypeRegistry $businessTypes,
        private readonly string $graphSchema,
    ) {
    }

    /**
     * @return array{graphSchema: string, nodeTypes: list<NodeDescriptor>, businessTypes: list<BusinessDescriptor>}
     */
    public function read(): array
    {
        $nodeTypes = $this->nodes->all();
        $businessTypes = $this->businessTypes->all();
        ksort($nodeTypes);
        ksort($businessTypes);
        $nodes = [];
        foreach ($nodeTypes as $key => $type) {
            $nodes[] = [
                'key' => $key,
                'type' => $type->type,
                'version' => $type->version,
                'labelSnippet' => $type->labelSnippet,
                'configurationSchema' => $type->configurationSchema,
                'configurationSchemaId' => $type->configurationSchemaId,
                'configurationSchemaHash' => $type->configurationSchemaHash,
                'draftConfigurationSchema' => $type->draftConfigurationSchema,
                'ports' => (object) $type->ports,
                'allowedEffects' => array_map(static fn (NodeEffectType $effect): string => $effect->value, $type->allowedEffects),
            ];
        }
        $businesses = [];
        foreach ($businessTypes as $key => $type) {
            $businesses[] = [
                'key' => $key,
                'type' => $type->type,
                'version' => $type->version,
                'labelSnippet' => $type->labelSnippet,
                'formSchema' => $type->formSchema,
                'formSchemaId' => $type->formSchemaId,
                'formSchemaHash' => $type->formSchemaHash,
            ];
        }

        return ['graphSchema' => $this->graphSchema, 'nodeTypes' => $nodes, 'businessTypes' => $businesses];
    }
}
