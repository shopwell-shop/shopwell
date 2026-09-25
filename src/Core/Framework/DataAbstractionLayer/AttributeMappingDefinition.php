<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class AttributeMappingDefinition extends MappingEntityDefinition
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(private readonly array $meta = [])
    {
    }

    public function getEntityName(): string
    {
        return $this->meta['entity_name'];
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [];
        foreach ($this->meta['fields'] as $field) {
            if (!isset($field['class'])) {
                continue;
            }

            $instance = new $field['class'](...$field['args']);
            if (!$instance instanceof Field) {
                continue;
            }

            foreach ($field['flags'] ?? [] as $flag) {
                $flagInstance = new $flag['class'](...$flag['args'] ?? []);

                if ($flagInstance instanceof Flag) {
                    $instance->addFlags($flagInstance);
                }
            }

            $fields[] = $instance;
        }

        // check for source entity is version-aware and attach reference version field
        $entity = $this->meta['source'];
        if ($this->registry->getByClassOrEntityName($entity)->isVersionAware()) {
            $fields[] = (new ReferenceVersionField($entity))->addFlags(new PrimaryKey(), new Required());
        }

        // check for reference entity is version-aware and attach reference version field
        $entity = $this->meta['reference'];
        if ($this->registry->getByClassOrEntityName($entity)->isVersionAware()) {
            $fields[] = (new ReferenceVersionField($entity))->addFlags(new PrimaryKey(), new Required());
        }

        return new FieldCollection($fields);
    }
}
