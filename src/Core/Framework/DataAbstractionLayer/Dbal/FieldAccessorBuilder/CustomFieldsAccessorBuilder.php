<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomField\CustomFieldService;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldsAccessorBuilder extends JsonFieldAccessorBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CustomFieldService $customFieldService,
        Connection $connection
    ) {
        parent::__construct($connection);
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof CustomFields) {
            return null;
        }

        /**
         * Possible paths / attribute names:
         * - propertyName.attribute_name -> attribute_name
         * - propertyName.attribute_name.foo -> attribute_name
         * - propertyName."attribute.name" -> attribute.name
         * - propertyName."attribute.name".foo -> attribute.name
         *
         * @var string $attributeName
         */
        $attributeName = preg_replace(
            '#^' . preg_quote($field->getPropertyName(), '#') . '\.("([^"]*)"|([^.]*)).*#',
            '$2$3',
            $accessor
        );
        $attributeField = $this->customFieldService->getCustomField($attributeName);

        $field->setPropertyMapping([$attributeField]);

        return parent::buildAccessor($root, $field, $context, $accessor);
    }
}
