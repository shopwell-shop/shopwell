<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Processing\Mapping;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class CriteriaBuilder
{
    public function __construct(private readonly EntityDefinition $definition)
    {
    }

    public function enrichCriteria(Config $config, Criteria $criteria): Criteria
    {
        foreach ($config->getMapping() as $mapping) {
            $tmpDefinition = $this->definition;
            $parts = explode('.', $mapping->getKey());

            $prefix = '';

            foreach ($parts as $assoc) {
                if ($assoc === 'extensions') {
                    continue; // extension associations must also be joined if the field is in the mapping
                }

                $field = $tmpDefinition->getField($assoc);
                if (!$field || !$field instanceof AssociationField) {
                    break;
                }
                $criteria->addAssociation($prefix . $assoc);
                $prefix .= $assoc . '.';
                $tmpDefinition = $field->getReferenceDefinition();
            }
        }

        return $criteria;
    }
}
