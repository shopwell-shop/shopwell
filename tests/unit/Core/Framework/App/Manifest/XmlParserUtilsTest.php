<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\XmlParserUtils;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(XmlParserUtils::class)]
class XmlParserUtilsTest extends TestCase
{
    public function testParseAttributes(): void
    {
        $element = $this->createDOMElement(['attr1' => 'value1', 'attr_2' => 'value2']);

        $result = XmlParserUtils::parseAttributes($element);

        static::assertSame(['attr1' => 'value1', 'attr2' => 'value2'], $result);
    }

    public function testParseAttributesPhpizesValueEvenWhenTypeIsString(): void
    {
        $element = $this->createDOMElement([
            'type' => 'string',
            'value' => '{"foo":"bar"}',
        ]);

        $result = XmlParserUtils::parseAttributes($element);

        static::assertSame(
            [
                'type' => 'string',
                'value' => ['foo' => 'bar'],
            ],
            $result
        );
    }

    public function testParseChildren(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildren($element);

        static::assertSame(['child1' => 'value1', 'child2' => 'value2'], $result);
    }

    public function testParseChildrenWithTransformer(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildren($element, static fn (\DOMElement $e) => strtoupper($e->nodeValue ?? ''));

        static::assertSame(['child1' => 'VALUE1', 'child2' => 'VALUE2'], $result);
    }

    public function testParseChildrenIgnoresNonDomElements(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMText('test'));

        $result = XmlParserUtils::parseChildren($element);

        static::assertEmpty($result);
    }

    public function testParseChildrenAsList(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildrenAsList($element);

        static::assertSame(['value1', 'value2'], $result);
    }

    public function testParseChildrenAsListWithTransformer(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMElement('child1', 'value1'));
        $element->appendChild(new \DOMElement('child2', 'value2'));

        $result = XmlParserUtils::parseChildrenAsList($element, static fn (\DOMElement $e) => strtoupper($e->nodeValue ?? ''));

        static::assertSame(['VALUE1', 'VALUE2'], $result);
    }

    public function testParseChildrenAsListIgnoresNonDomElements(): void
    {
        $element = $this->createDOMElement();
        $element->appendChild(new \DOMText('test'));

        $result = XmlParserUtils::parseChildrenAsList($element);

        static::assertEmpty($result);
    }

    public function testParseChildrenAndTranslate(): void
    {
        $document = new \DOMDocument();
        $element = $document->createElement('test');

        $nameEn = $document->createElement('name', 'EnglishName');
        $nameEn->setAttribute('lang', 'en-GB');

        $labelEn = $document->createElement('label', 'EnglishLabel');
        $labelEn->setAttribute('lang', 'en-GB');

        $nameZh = $document->createElement('name', 'ChineseName');
        $nameZh->setAttribute('lang', 'zh-CN');

        $labelZh = $document->createElement('label', 'ChineseLabel');
        $labelZh->setAttribute('lang', 'zh-CN');

        $version = $document->createElement('version', '1.5');

        $element->appendChild($nameEn);
        $element->appendChild($labelEn);
        $element->appendChild($nameZh);
        $element->appendChild($labelZh);
        $element->appendChild($version);

        $result = XmlParserUtils::parseChildrenAndTranslate($element, ['name', 'label']);

        $expectedResult = [
            'name' => [
                'en-GB' => 'EnglishName',
                'zh-CN' => 'ChineseName',
            ],
            'label' => [
                'en-GB' => 'EnglishLabel',
                'zh-CN' => 'ChineseLabel',
            ],
            'version' => '1.5',
        ];

        static::assertSame($expectedResult, $result);
    }

    public function testMapTranslatedTag(): void
    {
        $element = $this->createDOMElement();

        /** @var \DOMElement $en */
        $en = $element->appendChild(new \DOMElement('name', 'EnglishName'));
        $en->setAttribute('lang', 'en-GB');

        /** @var \DOMElement $zh */
        $zh = $element->appendChild(new \DOMElement('name', 'ChineseName'));
        $zh->setAttribute('lang', 'zh-CN');

        $result = XmlParserUtils::mapTranslatedTag($en, []);

        static::assertSame(
            [
                'name' => [
                    'en-GB' => 'EnglishName',
                ],
            ],
            $result
        );

        $result = XmlParserUtils::mapTranslatedTag($zh, [
            'name' => [
                'en-GB' => 'EnglishName',
            ],
        ]);

        static::assertSame(
            [
                'name' => [
                    'en-GB' => 'EnglishName',
                    'zh-CN' => 'ChineseName',
                ],
            ],
            $result
        );
    }

    public function testKebabCaseToCamelCase(): void
    {
        static::assertSame('someValue', XmlParserUtils::kebabCaseToCamelCase('some-value'));
        static::assertSame('someValue', XmlParserUtils::kebabCaseToCamelCase('some_value'));
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createDOMElement(array $attributes = []): \DOMElement
    {
        $document = new \DOMDocument();
        $element = $document->createElement('test');

        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }

        return $element;
    }
}
