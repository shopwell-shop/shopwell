<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomField\Xml\CustomFieldTypes;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('framework')]
class MediaSelectionField extends CustomFieldType
{
    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'componentName' => 'sw-media-field',
                'customFieldType' => 'media',
            ],
        ];
    }
}
