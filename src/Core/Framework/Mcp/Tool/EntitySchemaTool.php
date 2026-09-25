<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolGroup;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(
    name: 'shopwell-entity-schema',
    title: 'Entity Schema',
    description: 'Get the field and association schema of a Shopwell entity definition: field names, types, and associations for building shopwell-entity-search criteria. Returns {success, data: {fields: [...], associations: [...]}}. See shopwell://entities resource for all available entity names.'
)]
#[McpToolGroup('entity')]
class EntitySchemaTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(string $entity): string
    {
        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the shopwell://entities resource for available entity names.', $entity));
        }

        $definition = $this->registry->getByEntityName($entity);

        $fields = [];
        $associations = [];

        foreach ($definition->getFields() as $field) {
            if ($field instanceof AssociationField) {
                $associations[] = [
                    'name' => $field->getPropertyName(),
                    'type' => match (true) {
                        $field instanceof ManyToManyAssociationField => 'many-to-many',
                        $field instanceof OneToManyAssociationField => 'one-to-many',
                        $field instanceof ManyToOneAssociationField => 'many-to-one',
                        $field instanceof OneToOneAssociationField => 'one-to-one',
                        default => 'association',
                    },
                    'entity' => $field->getReferenceDefinition()->getEntityName(),
                ];

                continue;
            }

            $fields[] = [
                'name' => $field->getPropertyName(),
                'type' => match (true) {
                    $field instanceof IdField => 'uuid',
                    $field instanceof FkField => 'fk',
                    $field instanceof BoolField => 'bool',
                    $field instanceof IntField => 'int',
                    $field instanceof FloatField => 'float',
                    $field instanceof DateTimeField => 'datetime',
                    $field instanceof JsonField => 'json',
                    default => 'string',
                },
                'required' => $field->is(Required::class),
            ];
        }

        return $this->success([
            'entity' => $entity,
            'fields' => $fields,
            'associations' => $associations,
        ]);
    }
}
