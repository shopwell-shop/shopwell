<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\DataResolver\Element;

use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
abstract class AbstractCmsElementResolver implements CmsElementResolverInterface
{
    /**
     * @return mixed|Entity|Struct|null
     */
    protected function resolveEntityValue(?Entity $entity, string $path)
    {
        if ($entity === null) {
            return null;
        }

        $value = $entity;
        $entityPath = explode('.', $path);

        // if property does not exist, try to omit the first key as it may contain the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        while ($entityPath !== []) {
            $entityPathPart = array_shift($entityPath);

            if ($value === null) {
                break;
            }

            try {
                $parentValue = $value;
                switch (true) {
                    case \is_array($value):
                        $value = $value[$entityPathPart] ?? null;

                        break;
                    case $value instanceof Entity:
                        $value = $value->get($entityPathPart);

                        break;
                    case $value instanceof Struct:
                        $value = $value->getVars();
                        $value = $value[$entityPathPart] ?? null;

                        break;
                    default:
                        $value = null;
                }

                // On the last element, try to get the translation if nothing else was found
                if ($value === null && $parentValue instanceof Entity) {
                    $value = $parentValue->getTranslation($entityPathPart);
                }
            } catch (PropertyNotFoundException|\InvalidArgumentException $ex) {
                if (!$smartDetect) {
                    throw $ex;
                }
            }

            if ($value === null && !$smartDetect) {
                break;
            }

            $smartDetect = false;
        }

        return $value;
    }

    protected function resolveEntityValueToString(?Entity $entity, string $path, EntityResolverContext $resolverContext): string
    {
        $content = $this->resolveEntityValue($entity, $path);

        if ($content instanceof \DateTimeInterface) {
            $dateFormatter = new \IntlDateFormatter(
                $resolverContext->getRequest()->getLocale(),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::MEDIUM
            );
            $content = $dateFormatter->format($content);
        }

        if ($content === null || \is_scalar($content) || (\is_object($content) && \method_exists($content, '__toString'))) {
            return (string) $content;
        }

        return $path;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    protected function resolveDefinitionField(EntityDefinition $definition, string $path): ?Field
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Function will be removed without replacement');

        $value = null;
        $parts = explode('.', $path);
        $fields = $definition->getFields();

        // if property does not exist, try to omit the first key as it may contain the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        while ($parts !== []) {
            $part = array_shift($parts);
            $value = $fields->get($part);

            if ($value === null && !$smartDetect) {
                break;
            }

            $smartDetect = false;

            if ($value instanceof AssociationField) {
                $fields = $value->getReferenceDefinition()->getFields();
            }
        }

        return $value;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    protected function resolveCriteriaForLazyLoadedRelations(
        EntityResolverContext $resolverContext,
        FieldConfig $config
    ): ?Criteria {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Function will be removed without replacement');

        $field = $this->resolveDefinitionField($resolverContext->getDefinition(), $config->getStringValue());
        if ($field === null) {
            return null;
        }

        $key = null;
        $refDef = null;

        // resolve reverse side to fetch data afterwards
        if ($field instanceof ManyToManyAssociationField) {
            $key = $this->getKeyByManyToMany($field);
            $refDef = $field->getToManyReferenceDefinition();
        } elseif ($field instanceof OneToManyAssociationField) {
            $key = $this->getKeyByOneToMany($field);
            $refDef = $field->getReferenceDefinition();
        }

        if (!$key || !$refDef) {
            return null;
        }

        $key = $refDef->getEntityName() . '.' . $key;

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter($key, $resolverContext->getEntity()->getUniqueIdentifier())
        );

        return $criteria;
    }

    protected function resolveEntityValues(EntityResolverContext $resolverContext, string $content): ?string
    {
        // https://regex101.com/r/idIfbk/1
        return preg_replace_callback(
            '/{{\s*(?<property>[\w.\d]+)\s*}}/',
            function ($matches) use ($resolverContext) {
                try {
                    return $this->resolveEntityValueToString($resolverContext->getEntity(), $matches['property'], $resolverContext);
                } catch (PropertyNotFoundException|\InvalidArgumentException) {
                    return $matches[0];
                }
            },
            $content
        );
    }

    private function getKeyByManyToMany(ManyToManyAssociationField $field): ?string
    {
        $referenceDefinition = $field->getReferenceDefinition();

        $manyToMany = $field->getToManyReferenceDefinition()->getFields()
            ->firstWhere(static fn (Field $field) => $field instanceof ManyToManyAssociationField && $field->getReferenceDefinition() === $referenceDefinition);

        if (!$manyToMany instanceof ManyToManyAssociationField) {
            return null;
        }

        return $manyToMany->getPropertyName() . '.' . $manyToMany->getReferenceField();
    }

    private function getKeyByOneToMany(OneToManyAssociationField $field): ?string
    {
        $referenceDefinition = $field->getReferenceDefinition();

        $manyToOne = $field->getReferenceDefinition()->getFields()
            ->firstWhere(static fn (Field $field) => $field instanceof ManyToOneAssociationField && $field->getReferenceDefinition() === $referenceDefinition);

        if (!$manyToOne instanceof ManyToOneAssociationField) {
            return null;
        }

        return $manyToOne->getPropertyName() . '.' . $manyToOne->getReferenceField();
    }
}
