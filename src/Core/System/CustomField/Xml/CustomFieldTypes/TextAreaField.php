<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomField\Xml\CustomFieldTypes;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('framework')]
class TextAreaField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    /**
     * @var array<string, string>
     */
    protected array $placeholder = [];

    /**
     * @return array<string, string>
     */
    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::HTML,
            'config' => [
                'placeholder' => $this->placeholder,
                'componentName' => 'sw-text-editor',
                'customFieldType' => 'textEditor',
            ],
        ];
    }
}
