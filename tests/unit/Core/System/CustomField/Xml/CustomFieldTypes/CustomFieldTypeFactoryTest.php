<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Exception\CustomFieldTypeNotFoundException;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomField\Xml\CustomFieldTypes\CustomFieldTypeFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFieldTypeFactory::class)]
class CustomFieldTypeFactoryTest extends TestCase
{
    public function testCreateFromXmlThrowsExceptionOnInvalidTag(): void
    {
        $this->expectExceptionObject(new CustomFieldTypeNotFoundException('invalid'));
        CustomFieldTypeFactory::createFromXml(new \DOMElement('invalid'));
    }

    public function testTranslatedForTag(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/custom-field-type-factory.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $field = $customFieldSet->getFields()[0];
        static::assertSame('bool_field', $field->getName());
        static::assertSame([
            'en-GB' => 'Test bool field',
            'de-DE' => 'Test bool field',
        ], $field->getLabel());
        static::assertSame([
            'en-GB' => 'Help text',
            'de-DE' => 'Help text',
        ], $field->getHelpText());
    }
}
