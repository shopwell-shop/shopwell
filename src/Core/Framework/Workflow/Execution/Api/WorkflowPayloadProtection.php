<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * Runtime payloads are exposed by authorized presenters, never by generic entity APIs.
 * The attribute's api: false still inherits Admin API visibility from the base DAL field.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowPayloadProtection extends EntityExtension
{
    /**
     * @param list<string> $fields
     */
    public function __construct(private readonly string $entityName, private readonly array $fields)
    {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function modifyFields(FieldCollection $collection): void
    {
        foreach ($collection as $field) {
            if (\in_array($field->getPropertyName(), $this->fields, true)) {
                $field->removeFlag(ApiAware::class);
            }
        }
    }
}
