<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomField\Xml\CustomFieldTypes\MultiSelectField;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MultiSelectField::class)]
class MultiSelectFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/multi-select-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $multiSelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(MultiSelectField::class, $multiSelectField);
        static::assertSame('test_multi_select_field', $multiSelectField->getName());
        static::assertSame([
            'en-GB' => 'Test multi-select field',
        ], $multiSelectField->getLabel());
        static::assertSame([], $multiSelectField->getHelpText());
        static::assertSame(1, $multiSelectField->getPosition());
        static::assertSame(['en-GB' => 'Choose your options...'], $multiSelectField->getPlaceholder());
        static::assertFalse($multiSelectField->getRequired());
        static::assertSame([
            'first' => [
                'en-GB' => 'First',
                'zh-CN' => '第一项',
            ],
            'second' => [
                'en-GB' => 'Second',
            ],
        ], $multiSelectField->getOptions());
    }

    public function testToEntityPayload(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/multi-select-field.xml');
        static::assertNotNull($manifest->getCustomFields());

        $multiSelectField = $manifest->getCustomFields()->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(MultiSelectField::class, $multiSelectField);

        static::assertEquals([
            'name' => 'test_multi_select_field',
            'type' => 'select',
            'config' => [
                'label' => [
                    'en-GB' => 'Test multi-select field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'placeholder' => [
                    'en-GB' => 'Choose your options...',
                ],
                'componentName' => 'sw-multi-select',
                'customFieldType' => 'select',
                'options' => [
                    [
                        'label' => [
                            'en-GB' => 'First',
                            'zh-CN' => '第一项',
                        ],
                        'value' => 'first',
                    ],
                    [
                        'label' => [
                            'en-GB' => 'Second',
                        ],
                        'value' => 'second',
                    ],
                ],
            ],
        ], $multiSelectField->toEntityPayload());
    }
}
