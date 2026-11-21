<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiCriteriaAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\BucketAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class ApiCriteriaValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $registry)
    {
    }

    public function validate(string $entity, Criteria $criteria, Context $context): void
    {
        $definition = $this->registry->getByEntityName($entity);

        foreach ($criteria->getAllFields() as $accessor) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor);

            foreach ($fields as $field) {
                if (!$field instanceof Field) {
                    continue;
                }

                if ($field->getFlag(ApiCriteriaAware::class)) {
                    continue;
                }

                EntityDefinitionQueryHelper::validateFieldIsApiAware($field, $accessor, $context);

                $runtime = $field->getFlag(Runtime::class);

                if ($runtime !== null) {
                    throw DataAbstractionLayerException::runtimeFieldInCriteria($accessor);
                }
            }
        }

        // Field-path permissions above apply to every source; only target-definition matching is Store API specific.
        $source = $context->getSource();
        if ($source instanceof AdminApiSource || $source instanceof SystemSource) {
            return;
        }

        foreach ($criteria->getAggregations() as $aggregation) {
            $this->validateEntityAggregations($definition, $aggregation);
        }
    }

    /**
     * An entity aggregation loads the entities of its `definition` by the values of its `field`, without any further
     * scoping. Outside the Admin API the definition must therefore be the one the field actually references, so a
     * request cannot pick an arbitrary entity type to load.
     */
    private function validateEntityAggregations(EntityDefinition $definition, Aggregation $aggregation): void
    {
        if ($aggregation instanceof BucketAggregation && $aggregation->getAggregation() !== null) {
            $this->validateEntityAggregations($definition, $aggregation->getAggregation());
        }

        if (!$aggregation instanceof EntityAggregation) {
            return;
        }

        $referenced = $this->getReferencedDefinition($definition, $aggregation->getField());

        if ($referenced === null || $referenced->getEntityName() !== $aggregation->getEntity()) {
            throw DataAbstractionLayerException::accessorNotAllowedInApiScope($aggregation->getField());
        }
    }

    private function getReferencedDefinition(EntityDefinition $definition, string $accessor): ?EntityDefinition
    {
        $owner = $definition;
        $last = null;

        foreach (EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor) as $field) {
            if ($field instanceof ManyToManyAssociationField) {
                $owner = $field->getToManyReferenceDefinition();
            } elseif ($field instanceof AssociationField) {
                $owner = $field->getReferenceDefinition();
            }

            $last = $field;
        }

        if ($last instanceof FkField) {
            return $last->getReferenceDefinition();
        }

        if ($last instanceof IdField && $last->is(PrimaryKey::class)) {
            return $owner;
        }

        if ($last instanceof ManyToManyIdField) {
            $association = $owner->getField($last->getAssociationName());

            return $association instanceof ManyToManyAssociationField ? $association->getToManyReferenceDefinition() : null;
        }

        return null;
    }
}
